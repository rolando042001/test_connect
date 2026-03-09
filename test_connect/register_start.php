<?php
include "db.php";

$sql = "UPDATE register_state SET active=1, step=1 WHERE id=1";

if($conn->query($sql)){
    echo "REGISTER_STARTED";
}else{
    echo "ERROR";
}

?>