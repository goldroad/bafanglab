<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>URL request</title>
</head>

<body bgcolor="3399FF">
<div style=" margin-top:70px;color:#FFF; font-size:23px; text-align:center"> hello </font><br>
<font size="3" color="#FFFF00">

<?php
	$id=$_GET['id'];
	//连接数据库，主机，用户名，密码，数据库
	$conn = mysqli_connect("localhost","root","root","bafanglab");
	if(!$conn)
	{//连接失败会输出error+错误代码
		die("error:".mysqli_connect_error());
	}

	//数据库查询语句
	$sql="SELECT username,password FROM users WHERE id= ? LIMIT 0,1";
	$stmt  = $conn->prepare($sql);
	$stmt->bind_param("d",$id);
	$stmt->bind_result($name,$password);
	$stmt->execute();
	while($stmt ->fetch()){
    	echo "<br> -- $name -- $password";
	}
	$stmt ->close();
	$conn->close();
?>

</font> </div></br></br></br>
</body>
</html>