<?php
	date_default_timezone_set('Europe/Berlin');
	function buildUrl($ignorekey='') {
		$url=$_SERVER['PHP_SELF'];
		$first=true;
		foreach ($_REQUEST as $key => $val) {
			if ($key==$ignorekey) continue;
			if (is_array($val)) {
				foreach ($val as $key2 => $val2) {
					if ($first) {
						$url.='?';
						$first=false;
					}
					else $url.='&amp;';
					$url.=$key.'[]='.$val2;
				}
			} else {
				if ($first) {
					$url.='?';
					$first=false;
				}
				else $url.='&amp;';
				$url.=$key.'='.$val;
			}
		}
		return $url;
	}
	$db = new SQLite3('log.db');
	$db->exec("CREATE TABLE IF NOT EXISTS value (valueid INTEGER PRIMARY KEY ASC, value REAL, daemonid INTEGER, time TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
	$db->exec("CREATE TABLE IF NOT EXISTS daemon (daemonid INTEGER PRIMARY KEY ASC, unit TEXT, name TEXT UNIQUE, shortname TEXT UNIQUE, uid TEXT UNIQUE);");
	if (isset($_REQUEST["uid"]) && isset($_REQUEST["value"])) {
		for ($i = 0; $i < sizeof($_REQUEST['uid']); $i++) {
			echo "Adding value ".$i.": ".$_REQUEST["value"][$i]."; uid: ".$_REQUEST['uid'][$i].";";
			$stmt = $db->prepare('SELECT daemonid FROM daemon WHERE uid = :uid');
			$stmt->bindValue(':uid', $_REQUEST["uid"][$i], SQLITE3_TEXT);
			$results = $stmt->execute();
			$row = $results->fetchArray();
			$daemonid = 0;
			if (isset($row['daemonid'])) {
				$daemonid = $row['daemonid'];
			} else {
				$stmt = $db->prepare('INSERT INTO daemon (uid, name, shortname) VALUES (:uid, :uid, :uid);');
				$stmt->bindValue(':uid', $_REQUEST["uid"][$i], SQLITE3_TEXT);
				$stmt->execute();
				$daemonid = $db->lastInsertId();
			}

			$stmt = $db->prepare('INSERT INTO value (daemonid, value) VALUES (:daemonid, :value);');
			$stmt->bindValue(':daemonid', $daemonid, SQLITE3_INTEGER);
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
		$msg='		<div class="message">Sensor '.$_REQUEST['shortname']." with name &quot;".$_REQUEST['name']."&quot; and id &quot;".$daemonid."&quot; created.</div>\n";
	}
	else if (isset($_REQUEST['action'])&&$_REQUEST['action']=="edit"&&isset($_REQUEST['uid'])&&isset($_REQUEST['name'])&&isset($_REQUEST['shortname'])&&isset($_REQUEST['unit'])&&isset($_REQUEST['daemonid'])) {
		$stmt = $db->prepare('UPDATE daemon SET uid=:uid, name=:name, shortname=:shortname, unit=:unit WHERE daemonid=:daemonid;');
		$stmt->bindValue(':daemonid', $_REQUEST["daemonid"], SQLITE3_INTEGER);
		$stmt->bindValue(':name', $_REQUEST["name"], SQLITE3_TEXT);
		$stmt->bindValue(':shortname', $_REQUEST["shortname"], SQLITE3_TEXT);
		$stmt->bindValue(':unit', $_REQUEST["unit"], SQLITE3_TEXT);
		$stmt->bindValue(':uid', $_REQUEST["uid"], SQLITE3_TEXT);
		$stmt->execute();

		$msg='		<div class="message">Updated Sensor '.$_REQUEST['shortname']." with name &quot;".$_REQUEST['name']."&quot;.</div>\n";
	}
	else if (isset($_REQUEST['action'])&&$_REQUEST['action']=="delete"&&isset($_REQUEST['daemonid'])) {
		$stmt = $db->prepare('DELETE FROM daemon WHERE daemonid=:daemonid;');
		$stmt->bindValue(':daemonid', $_REQUEST["daemonid"], SQLITE3_INTEGER);
		$stmt->execute();
		$msg='		<div class="message">Sensor '.$_REQUEST['daemonid']." deleted.</div>\n";
	}
	$colors=array('#C0C0C0','#808080','#000000','#FF0000','#800000','#FFFF00','#808000','#00FF00','#008000','#00FFFF','#008080','#0000FF','#000080','#FF00FF','#800080');
?><!DOCTYPE html>
<html>
	<head>
		<title>Log DB</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<script src="Chart.min.js"></script>
		<script src="Chart.Scatter.min.js"></script>
		<style>
			 form.tableform {
				display: table;
			 }
			 form.tableform div {
				display: table-row;
			 }
			 form.tableform div input, form.tableform div lable, form.tableform div p {
				display: table-cell;
			 }
			 #chartbox {
				width: 800px;
				max-width: 100%;
			 }
			 #timebox {
				width: 100%;
			 }
			 #starttimebox {
				text-align: left;
				width:50%;
				float:left;
			 }
			 #endtimebox {
				text-align: right;
				width: 50%;
				float:right;
			 }
			 #chart {
				max-width:100%;
				height:auto;
			 }
		</style>
	</head>
	<body>

<?php if (isset($msg)) echo $msg;?>

		<h1>Messwerte</h1>
		<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="GET">
			<table>
				<tr>
					<th>Id</th>
					<th>Name</th>
					<th>Aktueller Wert</th>
					<th>Letzte Aktualisierung</th>
					<th>Plot</th>
					<th></th>
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
					<td><input type="checkbox" name="show[]" value="<?php echo $row['daemonid'];?>" <?php if (isset($_REQUEST['show']) && in_array($row['daemonid'],$_REQUEST['show'])) echo 'checked="checked"';?> onchange="this.form.submit()" /></td>
					<td><a href="?edit=<?php echo $row['daemonid'];?>#edit">Edit</a> <a href="?delete=<?php echo $row['daemonid'];?>#delete">Delete</a> </td>
				</tr>
<?php
	}
	$results->finalize();
?>
			</table>
		</form>

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
		<div id="chartbox">
			<canvas id="chart" width="800" height="400"></canvas>
			<script>
				var ctx = document.getElementById("chart").getContext("2d");
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
				new Chart(ctx).Scatter(data, {animation:false, scaleType: "date",bezierCurve: false,});
			</script>
			<div id="timebox">
				<div id="starttimebox">
					<a alt="Start 1 day earlier"  href="<?php echo buildUrl('starttime').'&amp;starttime='.($starttime-60*60*24);?>">&laquo;</a> 
					<a alt="Start 1 hour earlier" href="<?php echo buildUrl('starttime').'&amp;starttime='.($starttime-60*60);?>">&lsaquo;</a> 
					starttime
					<a alt="Start 1 hour later"   href="<?php echo buildUrl('starttime').'&amp;starttime='.($starttime+60*60);?>">&rsaquo;</a> 
					<a alt="Start 1 day later"    href="<?php echo buildUrl('starttime').'&amp;starttime='.($starttime+60*60*24);?>">&raquo;</a>
				</div>
				<div id="endtimebox">
					<a alt="End 1 day earlier"  href="<?php echo buildUrl('endtime').'&amp;endtime='.($endtime-60*60*24);?>">&laquo;</a> 
					<a alt="End 1 hour earlier" href="<?php echo buildUrl('endtime').'&amp;endtime='.($endtime-60*60);?>">&lsaquo;</a> 
					endtime
					<a alt="End 1 hour later"   href="<?php echo buildUrl('endtime').'&amp;endtime='.($endtime+60*60);?>">&rsaquo;</a> 
					<a alt="End 1 day later"    href="<?php echo buildUrl('endtime').'&amp;endtime='.($endtime+60*60*24);?>">&raquo;</a>
				</div>
			</div>
		</div>
<?php
	}
?>

		<h1>Add Sensor</h1>
		<form class="tableform" action="index.php" method="POST">
			<div>
				<lable for="uid">UID:</lable>
				<input id="uid" name="uid" type="text" required="required"/>
			</div>
			<div>
				<lable for="name">Name:</lable>
				<input id="name" name="name" type="text" required="required"/>
			</div>
			<div>
				<lable for="shortname">Kurzname:</lable>
				<input id="shortname" name="shortname" type="text" required="required" />
			</div>
			<div>
				<lable for="unit">Einheit:</lable>
				<input id="unit" name="unit" type="text" required="required" />
			</div>
			<div>
				<input type="hidden" name="action" value="adddaemon" />
				<input type="submit" value="Add" />
			</div>
		</form>

<?php
	if (isset($_REQUEST['edit'])) {
		$stmt = $db->prepare('SELECT daemonid, name, shortname, unit, uid FROM daemon WHERE daemonid = '.$_REQUEST['edit'].';');
		$stmt->bindValue(':id', $_REQUEST['edit']);
		$results = $stmt->execute();
		if ($row = $results->fetchArray()) {
?>
		<h1 name="edit">Edit Daemon</h1>
		<form class="tableform" action="index.php" method="POST">
			<div>
				<lable for="uid">UID:</lable>
				<input id="uid" name="uid" type="text" value="<?php echo $row['uid'];?>" required="required"/>
			</div>
			<div>
				<lable for="name">Name:</lable>
				<input id="name" name="name" type="text" value="<?php echo $row['name'];?>" required="required"/>
			</div>
			<div>
				<lable for="shortname">Kurzname:</lable>
				<input id="shortname" name="shortname" type="text" value="<?php echo $row['shortname'];?>" required="required" />
			</div>
			<div>
				<lable for="unit">Einheit:</lable>
				<input id="unit" name="unit" type="text" value="<?php echo $row['unit'];?>" required="required" />
			</div>
			<div>
				<input type="hidden" name="daemonid" value="<?php echo $row['daemonid'];?>" />
				<input type="hidden" name="action" value="edit" />
				<input type="submit" value="Modify" />
			</div>
		</form>

<?php
		}
	}
?>

<?php
	if (isset($_REQUEST['delete'])) {
		$stmt = $db->prepare('SELECT daemonid, name, shortname, unit, uid FROM daemon WHERE daemonid = '.$_REQUEST['edit'].';');
		$stmt->bindValue(':id', $_REQUEST['delete']);
		$results = $stmt->execute();
		if ($row = $results->fetchArray()) {
?>
		<h1 name="delete">Delete Daemon</h1>
		<form action="index.php" method="POST">
			<p>Delete Daemon <?php echo $row['shortname'];?> (<?php echo $row['name'];?>) with UID <?php echo $row['uid'];?> ?</p>
			<input type="hidden" name="daemonid" value="<?php echo $row['daemonid'];?>" />
			<input type="hidden" name="action" value="delete" />
			<input type="submit" value="Delete" />
		</form>

<?php
		}
	}
?>

	</body>
</html>
