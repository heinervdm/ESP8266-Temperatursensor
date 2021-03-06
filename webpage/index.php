<?php
	require_once("config.php");
	$timezone = 'Europe/Berlin';
	date_default_timezone_set($timezone);
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
if ($usemysql) {
	$db = new PDO('mysql:host='.$mysqlhost.';dbname='.$mysqldb, $mysqluser, $mysqlpwd);
} else {
 	$db = new PDO('sqlite:'.$sqlitefile);
}
	$db->exec("CREATE TABLE IF NOT EXISTS value (valueid INTEGER PRIMARY KEY ASC, value REAL, daemonid INTEGER, time TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
// 	$db->exec("DROP INDEX IF EXISTS valueidx;");
	$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS valueidx ON value (daemonid, unixtime);");
// 	$db->exec("ALTER TABLE value ADD COLUMN unixtime INTEGER;");
// 	$db->exec("UPDATE value SET unixtime=strftime('%s', time, 'localtime');");
	$db->exec("CREATE TABLE IF NOT EXISTS daemon (daemonid INTEGER PRIMARY KEY ASC, unit TEXT, name TEXT UNIQUE, shortname TEXT UNIQUE, uid TEXT UNIQUE, calfunc TEXT);");
	if (isset($_REQUEST["uid"]) && isset($_REQUEST["value"])) {
		for ($i = 0; $i < sizeof($_REQUEST['uid']); $i++) {
			echo "Adding value ".$i.": ".$_REQUEST["value"][$i]."; uid: ".$_REQUEST['uid'][$i].";";
			$stmt = $db->prepare('SELECT daemonid FROM daemon WHERE uid = :uid');
			$stmt->bindValue(':uid', $_REQUEST["uid"][$i], PDO::PARAM_STR);
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$daemonid = 0;
			if (isset($row['daemonid'])) {
				$daemonid = $row['daemonid'];
			} else {
				$stmt = $db->prepare('INSERT INTO daemon (uid, name, shortname) VALUES (:uid, :uid, :uid);');
				$stmt->bindValue(':uid', $_REQUEST["uid"][$i], PDO::PARAM_STR);
				$stmt->execute();
				$daemonid = $db->lastInsertId();
			}

			if (!$usemysql) {
				$stmt = $db->prepare("INSERT INTO value (daemonid, value, unixtime) VALUES (:daemonid, :value, strftime('%s', 'now', 'localtime'));");
			} else {
				$stmt = $db->prepare("INSERT INTO value (daemonid, value, unixtime) VALUES (:daemonid, :value, UNIX_TIMESTAMP());");
			}
			$stmt->bindValue(':daemonid', $daemonid, PDO::PARAM_INT);
			$stmt->bindValue(':value', (float)$_REQUEST["value"][$i], PDO::PARAM_STR);
			$ok = $stmt->execute();
			if (!$ok) {
				print_r($stmt->errorInfo());
			}
		}
 		exit;
	}
	else if (isset($_REQUEST['action'])&&$_REQUEST['action']=="adddaemon"&&isset($_REQUEST['uid'])&&isset($_REQUEST['name'])&&isset($_REQUEST['shortname'])&&isset($_REQUEST['unit'])) {
		$stmt = $db->prepare('INSERT INTO daemon (uid, name, shortname, unit, calfunc) VALUES (:uid, :name, :shortname, :unit, :calfunc);');
		$stmt->bindValue(':name', $_REQUEST["name"], PDO::PARAM_STR);
		$stmt->bindValue(':shortname', $_REQUEST["shortname"], PDO::PARAM_STR);
		$stmt->bindValue(':unit', $_REQUEST["unit"], PDO::PARAM_STR);
		$stmt->bindValue(':uid', $_REQUEST["uid"], PDO::PARAM_STR);
		$stmt->bindValue(':calfunc', $_REQUEST["calfunc"], PDO::PARAM_STR);
		$stmt->execute();

		$daemonid = $db->lastInsertId();
		$msg='		<div class="message">Sensor '.$_REQUEST['shortname']." with name &quot;".$_REQUEST['name']."&quot; and id &quot;".$daemonid."&quot; created.</div>\n";
	}
	else if (isset($_REQUEST['action'])&&$_REQUEST['action']=="edit"&&isset($_REQUEST['uid'])&&isset($_REQUEST['name'])&&isset($_REQUEST['shortname'])&&isset($_REQUEST['unit'])&&isset($_REQUEST['daemonid'])) {
		$stmt = $db->prepare('UPDATE daemon SET uid=:uid, name=:name, shortname=:shortname, unit=:unit, calfunc=:calfunc WHERE daemonid=:daemonid;');
		$stmt->bindValue(':daemonid', $_REQUEST["daemonid"], PDO::PARAM_INT);
		$stmt->bindValue(':name', $_REQUEST["name"], PDO::PARAM_STR);
		$stmt->bindValue(':shortname', $_REQUEST["shortname"], PDO::PARAM_STR);
		$stmt->bindValue(':unit', $_REQUEST["unit"], PDO::PARAM_STR);
		$stmt->bindValue(':uid', $_REQUEST["uid"], PDO::PARAM_STR);
		$stmt->bindValue(':calfunc', $_REQUEST["calfunc"], PDO::PARAM_STR);
		$stmt->execute();

		$msg='		<div class="message">Updated Sensor '.$_REQUEST['shortname']." with name &quot;".$_REQUEST['name']."&quot;.</div>\n";
	}
	else if (isset($_REQUEST['action'])&&$_REQUEST['action']=="delete"&&isset($_REQUEST['daemonid'])) {
		$stmt = $db->prepare('DELETE FROM daemon WHERE daemonid=:daemonid;');
		$stmt->bindValue(':daemonid', $_REQUEST["daemonid"], PDO::PARAM_INT);
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
	$stmt = $db->prepare('SELECT d.daemonid, shortname, unit, uid, value, unixtime, calfunc FROM daemon d JOIN (SELECT MAX(time) as maxtime, daemonid FROM value GROUP BY daemonid) m ON m.daemonid =  d.daemonid JOIN value v ON v.time = m.maxtime AND m.daemonid = v.daemonid ORDER BY daemonid;');
	$ok = $stmt->execute();
	if (!$ok) {
		print_r($stmt->errorInfo());
	}
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
?>
				<tr>
					<td alt="<?php echo $row['uid'];?>"><?php echo $row['daemonid'];?></td>
					<td><?php echo $row['shortname'];?></td>
					<td>
						<?php
							if (!empty($row['calfunc']) && strpos($row['calfunc'], '$x') !== false) {
								$x = $row['value'];
								eval('$value='.$row["calfunc"].';');
								echo $value." ".$row['unit'];
							} else {
								echo $row['value']." ".$row['unit'];
							}
						?>
					</td>
					<td>
					<?php
					$dt = new DateTime();
					$tz = new DateTimeZone($timezone); 
					$dt->setTimestamp($row["unixtime"]);
					$dt->setTimezone($tz);
					echo $dt->format('d.m.Y H:i:s');
					?>
					</td>
					<td><input type="checkbox" name="show[]" value="<?php echo $row['daemonid'];?>" <?php if (isset($_REQUEST['show']) && in_array($row['daemonid'],$_REQUEST['show'])) echo 'checked="checked"';?> onchange="this.form.submit()" /></td>
					<td><a href="?edit=<?php echo $row['daemonid'];?>#edit">Edit</a> <a href="?delete=<?php echo $row['daemonid'];?>#delete">Delete</a> </td>
				</tr>
<?php
	}
// 	$results->finalize();
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
		$stmt = $db->prepare('SELECT daemonid, name, shortname, unit, calfunc FROM daemon WHERE daemonid IN ('.$ids.');');
		for ($i=0;$i<sizeof($_REQUEST['show']);$i++) {
			$stmt->bindValue(':id'.$i, $_REQUEST['show'][$i]);
		}
		$stmt->execute();
		$i=0;
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$c = $i + 10;
			$data[$i]['label'] = $row['name'];
			$data[$i]['strokeColor'] = $colors[$i%sizeof($colors)];
			$data[$i]['pointColor'] = $colors[$i%sizeof($colors)];
			$data[$i]['pointStrokeColor'] = '#fff';
			$data[$i]['data'] = array();
			$stmt2 = $db->prepare("SELECT value, unixtime FROM value WHERE daemonid=:id AND unixtime BETWEEN :starttime AND :endtime ORDER BY unixtime ASC;");
			$stmt2->bindValue(':id', $row['daemonid']);
			$stmt2->bindValue(':starttime', $starttime);
			$stmt2->bindValue(':endtime', $endtime);
			$stmt2->execute();
			$j=0;
			while ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
	// 			$data[$i]['data'][$j]['x']=strftime("%Y-%m-%dT%H:%I:%S",$row2['time']);
				$data[$i]['data'][$j]['x']=$row2['unixtime']*1000;
				
				if (!empty($row['calfunc']) && strpos($row['calfunc'], '$x') !== false) {
					$x = $row2['value'];
					eval('$result='.$row["calfunc"].';');
					$data[$i]['data'][$j]['y']=$result;
				} else {
					$data[$i]['data'][$j]['y']=$row2['value'];
				}
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
		$stmt = $db->prepare('SELECT daemonid, name, shortname, unit, uid, calfunc FROM daemon WHERE daemonid = :id;');
		$stmt->bindValue(':id', $_REQUEST['edit']);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
				<lable for="calfunc">Kalibrationsfunktion: (muss $x enthalten)</lable>
				<input id="calfunc" name="calfunc" type="text" value="<?php echo $row['calfunc'];?>" />
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
		$stmt = $db->prepare('SELECT daemonid, name, shortname, unit, uid FROM daemon WHERE daemonid = :id;');
		$stmt->bindValue(':id', $_REQUEST['delete']);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
