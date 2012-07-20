<?php
	/**
	 * Status overview
	 * @author Michael Greenhill
	 */
	
	require_once("../config.inc.php");
	
	// get the active servers from database
	$servers = $db->select(
		SM_DB_PREFIX.'servers',
		array('active' => 'yes'),
		array('server_id', 'ip', 'port', 'label', 'type', 'status', 'active', 'last_online', 'last_check', 'rtime')
	);
	
	$updater 	= new smUpdaterStatus();
	$offline 	= array(); 
	$online		= array(); 
	
	$colours['offline']['background'] 	= "#a00000";
	$colours['offline']['foreground'] 	= "#f7cece";
	$colours['online']['background']	= "#53a000";
	$colours['online']['foreground']	= "#d8f7ce";
	
	foreach ($servers as $server) {
		#print "<pre>"; print_r($server); print "</pre>";
		
		$server['last_checked_nice'] = getRelativeTime($server['last_check']);
		
		if ($server['status'] == "off") {
			$offline[$server['server_id']] = $server;
		} else {
			$online[$server['server_id']] = $server;
		}
	}
	
	
?>

<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Status Monitor</title>
<meta http-equiv="refresh" content="30">
<style type="text/css">
	body {
		background: #000;
		color: #fff;
		padding: 20px; 
		margin: 0;
		font-family: Verdana, Geneva, sans-serif;
		font-size: 10pt;
	}
	
	h2, p { 
		margin: 0;
		padding: 0;
	}
	
	h2 {
		margin-bottom: 10px;
	}
	
	#offline {
		float: left; width: 300px; 
	}
	
		#offline .entity {
			background: <?php echo $colours['offline']['background']; ?>;
			color: <?php echo $colours['offline']['foreground']; ?>;
			border: 2px solid <?php echo $colours['offline']['foreground']; ?>; 
			border-radius: 3px;
			margin-bottom: 20px;
			padding: 10px;
			box-shadow: 0px 0px 5px #666;
		}
		
	#online {
		margin-left: 320px;
	}
	
		#online .entity {
			background: <?php echo $colours['online']['background']; ?>;
			color: <?php echo $colours['online']['foreground']; ?>;
			border: 2px solid <?php echo $colours['online']['foreground']; ?>; 
			border-radius: 3px;
			margin-bottom: 20px;
			padding: 10px;
			box-shadow: 0px 0px 5px #666;
			float: left;
			margin-right: 20px;
			width: 300px;
		}
</style>
</head>

<body>
	<div id="offline">
		<?php foreach ($offline as $id => $host) : ?>
		<div class="entity">
			<h2><?php echo $host['label']; ?></h2>
			<p>Offline since <?php echo $host['last_online']; ?></p>
			<p>Last checked <?php echo $host['last_checked_nice']; ?></p>
		</div>
		<?php endforeach; ?>
	</div>
	<div id="online">
		<?php foreach ($online as $id => $host) : ?>
		<div class="entity">
			<h2><?php echo $host['label']; ?></h2>
			<p>Offline since <?php echo $host['last_online']; ?></p>
			<p>Latency: <?php echo $host['rtime']; ?>s</p>
		</div>
		<?php endforeach; ?>
	</div>
</body>
</html>
