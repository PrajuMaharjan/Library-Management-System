<?php

$host="localhost";
$database="np03cs4a240217";
$user="np03cs4a240217";
$password="2bCpct0qAC";

try{
	$pdo=new PDO("mysql:host=$host;dbname=$database",$user,$password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}catch(PDOException $e){
	die("Database connection failed.");
}

?>