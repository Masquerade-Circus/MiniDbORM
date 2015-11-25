<?php
	/**
	 *  Small Db ORM
	 *  By Masquerade Circus
	 *  christian@masquerade-circus.net
	 */
	class MiniDbORM{

		function __construct($host, $database, $user, $password, $errormode = PDO::ERRMODE_EXCEPTION){
			$this->db = new PDO("mysql:host=$host;dbname=$database",$user, $password);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, $errormode);
		}

		function tableExists($table) {
			return $this->db->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
		}

		function getTableFields($table){
			$sql = $this->db->prepare("DESCRIBE $table");
			$sql->execute();
			return $sql->fetchAll(PDO::FETCH_COLUMN);
		}

		function save($table, $values){
			if (!$this->tableExists($table)) return false;

			$tableFields = $this->getTableFields($table);

			if (is_object($values)) $values = (array)$values;
			foreach($values as $field => $value) {
				$field = preg_replace('[^A-Za-z0-9_]', '', $field);
				if (array_search($field, $tableFields) !== false){
					$keys[] = $field;
					$binds[":$field"] = is_array($values[$field]) ? json_encode($values[$field]) : $values[$field];
					$bindnames[] = ":$field";
				} else {
					trigger_error("The field '$field' does not exists in the table $table", E_USER_NOTICE);
				}
			}

			$keys = join($keys, ',');
			$bindnames = join($bindnames, ',');
			$sql = "INSERT INTO $table ( $keys ) VALUES ( $bindnames )";
			$sth = $this->db->prepare($sql);
			$sth->execute($binds);
			return $this->db->lastInsertId();
		}

		function update($table, $values, $id, $idFieldName = 'id'){
			if (!$this->tableExists($table)) return false;

			$tableFields = $this->getTableFields($table);

			$binds = [':id' => $id];
			$bindnames = [];

			if (is_object($values)) $values = (array)$values;
			foreach($values as $field => $value) {
				$field = preg_replace('[^A-Za-z0-9_]', '', $field);
				if (array_search($field, $tableFields) !== false){
					$binds[":$field"] = is_array($values[$field]) ? json_encode($values[$field]) : $values[$field];
					$bindnames[] = "$field=:$field";
				} else {
					trigger_error("The field '$field' does not exists in the table $table", E_USER_NOTICE);
				}
			}

			$bindnames = join($bindnames, ',');
			$sql = 'UPDATE '.$table." SET $bindnames WHERE ".$idFieldName." = :id";
			$sth = $this->db->prepare($sql);
			$sth->execute($binds);
			return $id;
		}

		function load($table, $id, $idFieldName = 'id') {
			if (!$this->tableExists($table)) return false;

			$sql = 'SELECT * FROM '.$table.' WHERE '.$idFieldName.' = :id';
			$sth = $this->db->prepare($sql);
			$sth->execute([':id' => $id]);
			$result = $sth->fetchAll(PDO::FETCH_OBJ);

			return count($result) > 0 ? $result[0] : $result;
		}

		function loadAll($table, $options = []) {
			if (!$this->tableExists($table)) return false;

			$o = (object)array_merge([
				'where' => null,
				'limit' => null,
				'order' => null
			], $options);

			if (is_float($o->limit))
				$o->limit = strval($o->limit);

			$sql = 'SELECT * FROM '.$table;
			if ($o->where !== null)
				$sql .= ' WHERE '.$o->where;

			if ($o->order !== null)
				$sql .= ' ORDER BY '.$o->order;

			if ($o->limit !== null)
				$sql .= ' LIMIT '.$o->limit;

			$sth = $this->db->prepare($sql);
			$sth->execute();
			return $sth->fetchAll(PDO::FETCH_OBJ);
		}

		function sql($sql = null){
			$sth = $this->db->prepare($sql);
			$sth->execute();
			return $sth->fetchAll(PDO::FETCH_OBJ);
		}

		function delete($table, $id, $idFieldName = 'id'){
			if (!$this->tableExists($table)) return false;
			$sth = $this->db->prepare('DELETE FROM '.$table.' WHERE '.$idFieldName.' = '.$id);
			return $sth->execute();
		}

		function find($table, $where = null, $limit = 1) {
			if (!$this->tableExists($table)) return false;
			$sql = 'SELECT * FROM '.$table;
			if ($where !== null)
				$sql.= ' WHERE '.$where;
			if ($limit !== 0)
				$sql.= ' LIMIT '.$limit;
			$sth = $this->db->prepare($s);
			$sth->execute();
			$result = $sth->fetchAll(PDO::FETCH_OBJ);
			return $limit === 1 && count($result) > 0 ? $result[0] : $result;
		}
	}
