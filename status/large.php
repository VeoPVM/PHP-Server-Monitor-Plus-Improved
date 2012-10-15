<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>WHSC Status Monitor</title><meta http-equiv="refresh" content="45">
<style type="text/css">
	.clearfix:after {
		content: ".";
		display: block;
		clear: both;
		visibility: hidden;
		line-height: 0;
		height: 0;
	}
	 
	.clearfix {
		display: inline-block;
	}
	 
	html[xmlns] .clearfix {
		display: block;
	}
	 
	* html .clearfix {
		height: 1%;
	}
	
	body {
		background: #fff; 
		font-family: Verdana, Geneva, sans-serif;
		font-size: 10pt;
	}
	
	h2 {
		margin-top: 0;
	}
	
	table {
		width: 100%;
	}
	
	table td, table th {
		padding: 4px;
		border-left: 2px solid #fff;
	}
	
		table tr td:first-child, table tr th:first-child {
			border-left: none;
		}
		
		table tbody tr:nth-child(even) td {
			background: #ddd; 
		}
	
	table thead th {
		background: #53a000;
		color: #fff; 
	}
	
		table thead th.error {
			background: #a00000;
		}
	
		table thead th.neutral {
			background: #ccc;
		}
	
	table.large tbody td {
		font-size: 2em;
		text-align: center;
	}
	
	table.med tbody td {
		font-size: 1.2em;
		padding: 6px;
	}
	
	table tr.error td {
		background: #a00000 !important;
		color: #fff !important; 
		font-weight: bold;
	}
	
	div.graphs {
		float: right; 
		width: 600px;
		margin-left: 20px;
		min-height: 200px; 
	}
	
	div.graph-margin {
		margin-right: 620px;
	}
	
	h2.large-ok {
		font-size: 7em;
		color: darkgreen; 
		text-rendering: optimizelegibility;
		-webkit-font-smoothing: antialiased;
		text-shadow: 1px 1px 1px rgba(0,0,0,0.004);
	}
	
	h2.med-err {
		font-size: 4em; 
		margin: 20px 0;
		text-align: center; 
		color: darkred;
	}
	
	div.err-block {
		background: darkred; 
		color: #fff; 
		padding: 20px; 
		margin-bottom: 20px; 
		border-radius: 10px;
	}
	
	div.err-block h3 {
		font-size: 2em;
		margin: 0; 
	}
	
	div.err-block p {
		margin-bottom: 0; 
	}
</style>
</head>

<?php
	require_once("../config.inc.php");
	
	$syslog = new MySQLi(SYSLOG_DB_HOST, SYSLOG_DB_USER, SYSLOG_DB_PASS, SYSLOG_DB_NAME); 
	
	// Get the latest issues from Syslog
	$query = "SELECT DeviceReportedTime, Facility, Priority, FromHost, Message, NTSeverity
				FROM systemevents 
				ORDER BY ReceivedAt DESC 
				LIMIT 0, 5";
	
	$events = array(); 
	
	if ($rs = $syslog->query($query)) {
		while ($row = $rs->fetch_assoc()) {
			$row['time'] = getRelativeTime($row['DeviceReportedTime']);
			$events[] = $row;
		}
	}
	
	// get the active servers from database
	$servers = $db->select(
		SM_DB_PREFIX.'servers',
		array('active' => 'yes'),
		array('server_id', 'ip', 'port', 'label', 'type', 'status', 'active', 'last_online', 'last_check', 'rtime', 'rtime_max', 'audible'),
		'',
		array('label')
	);
	
	$updater 	= new smUpdaterStatus();
	$offline 	= array(); 
	$online		= array(); 
	
	$ping_low	= 9999999999999;
	$ping_high	= 0;
	$ping_total	= 0;
	$audible	= false;
	
	foreach ($servers as $server) {
		$server['last_checked_nice'] = getRelativeTime($server['last_check']);
		
		if ($server['rtime'] < $ping_low) {
			$ping_low = $server['rtime']; 
		}
		
		if ($server['rtime'] > $ping_high) {
			$ping_high = $server['rtime'];
		}
		
		$ping_total = $ping_total + $server['rtime'];
		
		if ($server['status'] == "off") {
			$offline[$server['server_id']] = $server;
			
			if ($server['audible'] == "yes") {
				$audible = true;
			}
		} else {
			$online[$server['server_id']] = $server;
		}
	}
	
	//$offline[0] = $online[1]; 
	
	// Are we testing the audible alert?
	#$test = true;
	$test = false;
?>

<body>
	<?php if ($audible == true || $test == true) : ?>
	<audio src="IT-Crowd-Dramatic-Impact-1.mp3" autoplay></audio>
	<?php endif; ?>
	<div class="graphs">
		<h2>Graphs</h2>
		<p><strong>Router</strong></p>
		<img src="http://broadband.doe.wan/common/graph_unified.cgi?profile=mrtg_new&routerid=29808&hsize=513&vsize=100&period=1h&notitle&nolegend" />
		<p><strong>VicSMART</strong></p>
		<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1374&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
		<p><strong>WLC</strong></p>
		<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1224&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
		<p><strong>WHSC NAS01</strong></p>
		<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1444&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
		<p><strong>WHSC NAS02</strong></p>
		<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1445&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
	</div>
	
	<div class="graph-margin">
		<h2>Device status</h2>
		<p>Data current as at <strong><?php echo date("F j, Y, g:i a"); ?></strong></p>
		
		<table cellspacing="0" cellpadding="0" class="large">
			<thead>
				<tr>
					<th>Total devices</th>
					<th>Average response time</th>
					<th># Up</th>
					<th class="<?php if (count($offline)) : ?>error<?php else: ?>neutral<?php endif; ?>"># Down</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo count($servers); ?></td>
					<td><?php echo round($ping_total / count($server), 4); ?>s</td>
					<td><?php echo count($online); ?></td>
					<td><?php echo count($offline); ?></td>
				</tr>
			</tbody>
		</table>
		
		<?php if (count($offline)) : ?>
		<h2 class="med-err"><?php if (count($offline) == 1) : ?>1 device<?php else: echo count($offline); ?> devices<?php endif; ?> down</h2>
		<?php foreach ($offline as $id => $data) : ?>
		<div class="err-block">
			<h3 class="err"><?php echo $data['label']; ?></h3>
			<p><strong>Offline since <?php echo $data['last_online']; ?></strong></p>
		</div>
		<?php endforeach; ?>
		<?php else: ?>
		<div style="text-align: center;margin-top: 150px;">
			<h2 class="large-ok">All systems online</h2>
			<img src="tick-clip-art.jpg" style="margin: auto;" />
		</div>
		<?php endif; ?>
		<?php /*
		<table cellspacing="0" cellpadding="0" class="med">
			<thead>
				<tr>
					<th>Host name</th>
					<th>Address</th>
					<th>Last online</th>
					<th>Reponse time</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($servers as $server): ?>
				
				<?php if ($server['status'] == "off") : ?>
				<tr class="error">
				<?php else: ?>
				<tr>
				<?php endif; ?>
					<td><?php echo $server['label']; ?></td>
					<td><?php echo $server['ip']; ?></td>
					<td><?php echo $server['last_online']; ?></td>
					<td><?php echo round($server['rtime'], 3); ?>s</td>
					<td><?php echo $server['status']; ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		*/ ?>
	</div>
</body>
</html>
