<?php
function forma()
{
	echo("
<form action=j.php method=GET>
<input type=text name=n5>
<input type=submit name=submit>
</form>
");
}
if(empty($_GET['submit'])){
	forma();
} else {
	setcookie("n5", $_GET['n5'], time()+365*86400);
	$n=date("z",mktime(0,0,0,11,1,2010))-date("z",mktime(0,0,0,10,1,2010));
	$nn=date("w", mktime(0,0,0,10,1,2010));
	$k=1;
	print("<table border=1>");
	for($i=1;$i<=6;$i++)
	{print ("<tr>");
	for ($j=1;$j<=7;$j++)
	{
		if($k<=$n)
		{if (($i==1)&&($j<$nn)){print("<td></td>");}
		else {
			if(($j==6)||($j==7)) {print("<td bgcolor=red>");print $k;print("</td>");$k++;}
			else{
				if ($_COOKIE['n5']==$k) {print("<td bgcolor=green>");print $k;print("</td>");$k++;}
				else{
					print("<td>"); print $k; print("</td>");$k++;}
			}
		}
		}
		else break;}
		print("</tr>");
	}
	print("</table>");
}
?>
