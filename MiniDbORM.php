<?php

	/**
	 *  Small Db ORM
	 *  By Masquerade Circus
	 *  christian@masquerade-circus.net
	 */
	class MiniDbORM{

		function __construct($host, $database, $user, $password, $errormode = null, $sql = false){
			$this->host = $host;
			$this->database = $database;
			$this->user = $user;
			$this->password = $password;
			$this->sql = $sql;
			$this->pdo = true;
			$extension = $sql ? 'php_sqlsrv_56_ts' : 'php_mysql';

			if ( !extension_loaded('pdo') || !extension_loaded($extension))
				$this->pdo = false;

			if ($this->pdo){
				if ($errormode = null)
					$errormode = PDO::ERRMODE_EXCEPTION;
				$sql ?
					$this->db = new PDO("sqlsrv:server = $host; Database = $database",$user, $password) :
					$this->db = new PDO("mysql:host=$host;dbname=$database",$user, $password);

				$this->db->setAttribute(PDO::ATTR_ERRMODE, $errormode);
			}
		}

		function query($query){
			$con = mysql_connect($this->host, $this->user, $this->password);
			mysql_select_db($this->database);
			$result_query = mysql_query($query);

			$results = array();
			while ($line = mysql_fetch_array($result_query, MYSQL_ASSOC))
				array_push($results, (object)$line);
			mysql_free_result($result_query);

			if (count($results) == 0)
				$results = $result_query;

			mysql_close($con);
			return $results;
		}

		function tableExists($table){
			if ($this->pdo){
				if ($this->sql){
					$exists = $this->db->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.Tables WHERE TABLE_NAME = '$table'");
					$exists->execute();
					$array = $exists->fetchAll(PDO::FETCH_OBJ);
					return count($array) > 0;
				}

				return $this->db->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
			}

			return count($this->query("SHOW TABLES LIKE '$table'")) > 0;
		}

		function getTableFields($table){
			if ($this->pdo){
				$sql = $this->db->prepare($this->sql ? "SP_COLUMNS $table" : "DESCRIBE $table");
				$sql->execute();
				if ($this->sql){
					$tableFields = $sql->fetchAll(PDO::FETCH_OBJ);
					$fields = array();
					foreach ($tableFields as $key => $value)
						array_push($fields, $value->COLUMN_NAME);
					$tableFields = $fields;
				} else {
					$tableFields = $sql->fetchAll(PDO::FETCH_COLUMN);
				}
				return $tableFields;
			}

			$tableFields = $this->query("DESCRIBE $table");
			$fields = array();
			foreach ($tableFields as $key => $value)
				array_push($fields, $value->Field);

			return $fields;
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

			if ($this->pdo){
				$sth = $this->db->prepare($sql);
				$sth->execute($binds);
				return $this->db->lastInsertId();
			} else {
				foreach ($values as $field => $value){
					$field = preg_replace('[^A-Za-z0-9_]', '', $field);
					if (isset($binds[":$field"]))
						$sql = preg_replace("/:$field/", "'".$binds[":$field"]."'", $sql);
				}
			}

			return $this->query($sql);
		}

		function update($table, $values, $id, $idFieldName = 'id'){
			if (!$this->tableExists($table)) return false;

			$tableFields = $this->getTableFields($table);

			$binds = array(':id' => $id);
			$bindnames = array();

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

			if ($this->pdo){
				$sth = $this->db->prepare($sql);
				$sth->execute($binds);
				return $id;
			} else {
				foreach ($values as $field => $value){
					$field = preg_replace('[^A-Za-z0-9_]', '', $field);
					if (isset($binds[":$field"]))
						$sql = preg_replace("/:$field/", "'".$binds[":$field"]."'", $sql);
				}
				$sql = preg_replace("/:id/", $id, $sql);
			}

			return $this->query($sql);
		}

		function load($table, $id, $idFieldName = 'id') {
			if (!$this->tableExists($table)) return false;

			$sql = 'SELECT * FROM '.$table.' WHERE '.$idFieldName.' = :id';

			if ($this->pdo){
				$sth = $this->db->prepare($sql);
				$sth->execute(array(':id' => $id));
				$result = $sth->fetchAll(PDO::FETCH_OBJ);
			} else {
				$result = $this->query(preg_replace('/:id/', $id, $sql));
			}

			return count($result) > 0 ? $result[0] : $result;
		}

		function loadAll($table, $options = array()) {
			if (!$this->tableExists($table)) return false;

			$o = (object)array_merge(array(
				'where' => null,
				'limit' => null,
				'order' => null
			), $options);

			if (is_float($o->limit))
				$o->limit = strval($o->limit);

			$sql = ' * FROM '.$table;
			if ($o->where !== null)
				$sql .= ' WHERE '.$o->where;

			if ($o->order !== null)
				$sql .= ' ORDER BY '.$o->order;

			if ($o->limit !== null)
				if ($this->sql)
					$sql = ' TOP '. $o->limit . $sql;
					else
					$sql.= ' LIMIT '.$o->limit;

			if ($this->pdo){
				$sth = $this->db->prepare('SELECT'.$sql);
				$sth->execute();
				return $sth->fetchAll(PDO::FETCH_OBJ);
			}

			return $this->query('SELECT'.$sql);

		}

		function sql($sql = null){
			if ($this->pdo){
				$sth = $this->db->prepare($sql);
				$sth->execute();
				return $sth->fetchAll(PDO::FETCH_OBJ);
			}

			return $this->query($sql);
		}

		function delete($table, $id, $idFieldName = 'id'){
			if (!$this->tableExists($table)) return false;
			$sql = 'DELETE FROM '.$table.' WHERE '.$idFieldName.' = '.$id;
			if ($this->pdo){
				$sth = $this->db->prepare($sql);
				return $sth->execute();
			}
			$this->query($sql);
		}

		function find($table, $where = null, $limit = 1) {
			if (!$this->tableExists($table)) return false;
			$sql = ' * FROM '.$table;
			if ($where !== null)
				$sql.= ' WHERE '.$where;

			if ($limit !== 0)
				if ($this->sql)
					$sql = ' TOP '. $limit . $sql;
					else
					$sql.= ' LIMIT '.$limit;

			if ($this->pdo){
				$sth = $this->db->prepare('SELECT'. $sql);
				$sth->execute();
				$result = $sth->fetchAll(PDO::FETCH_OBJ);
			} else {
				$result = $this->query('SELECT'. $sql);
			}

			return $limit === 1 && count($result) > 0 ? $result[0] : $result;
		}
	}
