<?php
include "db.php";

$conn->query("UPDATE relay SET active=0");

echo "RESET";
?>