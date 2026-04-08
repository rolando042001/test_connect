<form action="process_signup.php" method="POST">
<h3>Add User Manually</h3>

First Name: <input type="text" name="first_name"><br><br>
Last Name: <input type="text" name="last_name"><br><br>
Email: <input type="email" name="email"><br><br>

Department:
<select name="department">
    <option value="IT">IT</option>
    <option value="CRIM">CRIM</option>
    <option value="ECE">ECE</option>
    <option value="HRM">HRM</option>
</select><br><br>

Username: <input type="text" name="username"><br><br>
Password: <input type="password" name="password"><br><br>

<button type="submit">Create</button>
</form>