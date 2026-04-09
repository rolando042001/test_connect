<!DOCTYPE html>
<html>
<head>
    <title>Signup</title>
</head>
<body>
<h2>Signup</h2>

<form action="process_signup.php" method="POST">
    First Name: <input type="text" name="first_name" required><br><br>
    Last Name: <input type="text" name="last_name" required><br><br>
    Email: <input type="email" name="email" required><br><br>

    Department:
    <select name="department" required>
        <option value="IT">IT</option>
        <option value="CRIM">CRIM</option>
        <option value="ECE">ECE</option>
        <option value="HRM">HRM</option>
        <option value="EDUC">EDUC</option>
        <option value="BA">BA</option>
        <option value="OTHER">OTHER</option>
    </select><br><br>

    Username: <input type="text" name="username" required><br><br>
    Password: <input type="password" name="password" required><br><br>

    <button type="submit">Signup</button>
</form>

</body>
</html>