<?php

$host="localhost";
$database="library_management_system";
$user="root";
$password="";

try{
	$pdo=new PDO("mysql:host=$host;dbname=$database",$user,$password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}catch(PDOException $e){
	die("Database connection failed.");
}

?>