<script>
var currentStep = -1;

function checkStep(){
    var xhr = new XMLHttpRequest();
    xhr.open("GET","check_step.php?user_id=<?php echo $_SESSION['user_id']; ?>",true);
    xhr.send();

    xhr.onload = function(){
        var step = parseInt(xhr.responseText.trim());

        console.log("Step:", step);

        if(step !== currentStep){
            currentStep = step;
            showStep(step);
        }

        // ALWAYS continue polling unless finished AND redirected
        if(step < 4){
            setTimeout(checkStep,1000);
        }
    }
}

function showStep(step){
    document.getElementById("step1").style.display="none";
    document.getElementById("step3").style.display="none";

    if(step == 1){
        document.getElementById("step1").style.display="block";
    }

    if(step == 3){
        document.getElementById("step3").style.display="block";
    }

    if(step == 4){
        alert("Enrollment Completed!");
        window.location="user_dashboard.php";
    }
}

checkStep();
</script>