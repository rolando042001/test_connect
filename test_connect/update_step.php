<?php
include "db.php";

if(isset($_GET['step'])){

    $step = intval($_GET['step']);

    $sql = "UPDATE register_state SET step='$step' WHERE id=1";

    if($conn->query($sql)){
        echo "STEP_UPDATED";
    }else{
        echo "ERROR";
    }

}else{
    echo "NO_STEP";
}

?>