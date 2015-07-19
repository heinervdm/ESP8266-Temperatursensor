<?php
	date_default_timezone_set('Europe/Berlin');
	$db = new SQLite3('log.db');
	$db->exec("CREATE TABLE IF NOT EXISTS value (valueid INTEGER PRIMARY KEY ASC, value REAL, daemonid INTEGER, time TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
	$db->exec("CREATE TABLE IF NOT EXISTS daemon (daemonid INTEGER PRIMARY KEY ASC, unit TEXT, name TEXT UNIQUE, shortname TEXT UNIQUE, uid TEXT UNIQUE);");
	if (isset($_REQUEST["uid"]) && isset($_REQUEST["value"])) {
		for ($i = 0; $i < sizeof($_REQUEST['uid']); $i++) {
			echo "Adding value ".$i.": ".$_REQUEST["value"][$i]."; uid: ".$_REQUEST['uid'][$i].";";
			$stmt = $db->prepare('INSERT INTO value (daemonid, value) VALUES ((SELECT daemonid FROM daemon WHERE uid = :uid), :value);');
			$stmt->bindValue(':uid', $_REQUEST["uid"][$i], SQLITE3_TEXT);
			$stmt->bindValue(':value', (float)$_REQUEST["value"][$i], SQLITE3_FLOAT);
			$stmt->execute();
		}
		$db->close();
 		exit;
	}
	else if (isset($_REQUEST['action'])&&$_REQUEST['action']=="adddaemon"&&isset($_REQUEST['uid'])&&isset($_REQUEST['name'])&&isset($_REQUEST['shortname'])&&isset($_REQUEST['unit'])) {
		$stmt = $db->prepare('INSERT INTO daemon (uid, name, shortname, unit) VALUES (:uid, :name, :shortname, :unit);');
		$stmt->bindValue(':name', $_REQUEST["name"], SQLITE3_TEXT);
		$stmt->bindValue(':shortname', $_REQUEST["shortname"], SQLITE3_TEXT);
		$stmt->bindValue(':unit', $_REQUEST["unit"], SQLITE3_TEXT);
		$stmt->bindValue(':uid', $_REQUEST["uid"], SQLITE3_TEXT);
		$stmt->execute();

		$daemonid = $db->lastInsertRowID();
		$msg='<div class="message">Daemon '.$_REQUEST['shortname']." with name &quot;".$_REQUEST['name']."&quot; and id &quot;".$daemonid."&quot; created.</div>\n";
	}
	$colors=array('#C0C0C0','#808080','#000000','#FF0000','#800000','#FFFF00','#808000','#00FF00','#008000','#00FFFF','#008080','#0000FF','#000080','#FF00FF','#800080');
?><!DOCTYPE html>
<html>
	<head>
		<title>Log DB</title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
		<script src="Chart.min.js"></script>
		<script src="Chart.Scatter.min.js"></script>
	</head>
	<body>

<?php if (isset($msg)) echo $msg;?>

		<h1>Messwerte</h1>
		<table>
			<tr>
				<th>Id</th>
				<th>Name</th>
				<th>Aktueller Wert</th>
				<th>Letzte Aktualisierung</th>
			</tr>
<?php
	$results = $db->query('SELECT daemonid, shortname, unit, uid, value, datetime(time, \'localtime\') AS time FROM value NATURAL INNER JOIN daemon GROUP BY daemonid, unit, shortname, uid ORDER BY daemonid ASC;');
	while ($row = $results->fetchArray()) {
?>
			<tr>
				<td alt="<?php echo $row['uid'];?>"><?php echo $row['daemonid'];?></td>
				<td><?php echo $row['shortname'];?></td>
				<td><?php echo $row['value'];?> <?php echo $row['unit'];?></td>
				<td><?php echo date('d.m.Y H:i:s',strtotime($row['time']));?></td>
			</tr>
<?php
	}
	$results->finalize();
?>
		</table>

<?php
	if (isset($_REQUEST['show'])) {
		$starttime = time()-60*60*24;
		$endtime = time();
		if (isset($_REQUEST['starttime'])) {
			$starttime=$_REQUEST['starttime'];
		}
		if (isset($_REQUEST['endtime'])) {
			$endtime=$_REQUEST['endtime'];
		}
		$ids="";
		for ($i=0;$i<sizeof($_REQUEST['show']);$i++) {
			$ids.=':id'.$i;
			if ($i<sizeof($_REQUEST['show'])-1) $ids.=',';
		}
?>
		<canvas id="myChart" width="800" height="400"></canvas>
		<script>
			var ctx = document.getElementById("myChart").getContext("2d");
			var data =<?php
	$data = array();
	$stmt = $db->prepare('SELECT daemonid, name, shortname, unit FROM daemon WHERE daemonid IN ('.$ids.');');
	for ($i=0;$i<sizeof($_REQUEST['show']);$i++) {
		$stmt->bindValue(':id'.$i, $_REQUEST['show'][$i]);
	}
	$results = $stmt->execute();
	$i=0;
	while ($row = $results->fetchArray()) {
		$c = $i + 10;
		$data[$i]['label'] = $row['name'];
		$data[$i]['strokeColor'] = $colors[$i%sizeof($colors)];
		$data[$i]['pointColor'] = $colors[$i%sizeof($colors)];
		$data[$i]['pointStrokeColor'] = '#fff';
		$data[$i]['data'] = array();
		$stmt2 = $db->prepare("SELECT value, strftime('%s',time, 'localtime')*1000 AS time FROM value WHERE daemonid=:id AND time BETWEEN datetime(:starttime, 'unixepoch', 'localtime') AND datetime(:endtime, 'unixepoch', 'localtime') ORDER BY time ASC;");
		$stmt2->bindValue(':id', $row['daemonid']);
		$stmt2->bindValue(':starttime', $starttime);
		$stmt2->bindValue(':endtime', $endtime);
		$results2 = $stmt2->execute();
		$j=0;
		while ($row2 = $results2->fetchArray()) {
// 			$data[$i]['data'][$j]['x']=strftime("%Y-%m-%dT%H:%I:%S",$row2['time']);
			$data[$i]['data'][$j]['x']=$row2['time'];
			$data[$i]['data'][$j]['y']=$row2['value'];
			$j++;
		}
		$i++;
	}
	$json = json_encode($data);
// 	$json = preg_replace("/\"(\d+-\d+-\d+T\d+:\d+:\d+)\"/i","new Date(\"\\1\")", $json);
// 	$json = str_replace(",",",\n",$json);
	echo $json;
?>;
			new Chart(ctx).Scatter(data, {scaleType: "date",bezierCurve: false,});
		</script>
<?php
}
?>

		<h1>Add Daemon</h1>
		<form action="index.php" method="POST">
			<lable for="uid">UID:</lable>
			<input id="uid" name="uid" type="text" required="required"/>
			<br />

			<lable for="name">Name:</lable>
			<input id="name" name="name" type="text" required="required"/>
			<br />

			<lable for="shortname">Kurzname:</lable>
			<input id="shortname" name="shortname" type="text" required="required" />
			<br />

			<lable for="unit">Einheit:</lable>
			<input id="unit" name="unit" type="text" required="required" />
			<br />

			<input type="hidden" name="action" value="adddaemon" />
			<input type="submit" value="Add" />
		</form>
	
	</body>
</html>
