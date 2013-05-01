<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<script src="jquery-1.7.2.min.js"></script>

<title>WHSC Status Monitor</title><meta http-equiv="refresh" content="180">
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
		padding: 0; 
		margin: 0;
		padding-top: 10px;
	}
	
	.slide {
		padding: 20px;
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
		
		table.border tbody tr:nth-child(even) td {
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
	
	/**
	 * Slideshow CSS
	 */
	
	#slideshow {
		position: relative; 
		width: 100%; 
		height: 100%; 
		padding: 0px;  
	}
	
	#slideshow > div { 
		position: absolute; 
		top: 10px; 
		left: 10px; 
		right: 10px; 
		bottom: 10px; 
	}
	
	#down {
		position: absolute; 
		top: 0; 
		font-size: 1.4em;
		width: 100%; 
		background: darkred; 
		height: 45px;
	}
	
	#down p {
		margin: 0;
		color: #fff; 
		padding: 10px;  
		position: absolute; 
		top: 0;
		left: 0;
	}
	
	#up {
		position: absolute; 
		top: 0; 
		font-size: 1.4em; 
		width: 100%; 
		background: #53a000;
		height: 45px; 
	}
	
	#up p {
		margin: 0; 
		color: #fff; 
		padding: 10px; 
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
				LIMIT 0, 35";
	
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
		$server['last_online_nice'] = getRelativeTime($server['last_online']);
		
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
	
	#print "<pre>"; print_r($events); die; 
	
	//$offline[0] = $online[1]; 
	
	// Are we testing the audible alert?
	#$test = true;
	$test = false;
?>

<body>
	<?php if (count($offline)) : ?>
	<div id="down">
		<?php foreach ($offline as $row) : ?>
		<p><?php echo $row['label']; ?> down since <?php echo $row['last_online_nice']; ?></p>
		<?php endforeach; ?>
	</div>
	<?php else: ?>
	<div id="up">
		<p>All monitored systems are OK!</p>
	</div>
	<?php endif; ?>
	
	<div id="slideshow">
		<div class="slide clearfix">
			<?php if ($audible == true || $test == true) : ?>
			<audio src="IT-Crowd-Dramatic-Impact-1.mp3" autoplay></audio>
			<?php endif; ?>
			<div class="graphs">
				<h1>Graphs</h1>
				<p><strong>Router</strong></p>
				<img src="http://broadband.doe.wan/common/graph_unified.cgi?profile=mrtg_new&routerid=29808&hsize=513&vsize=100&period=1h&notitle&nolegend" />
				<p><strong>VicSMART</strong></p>
				<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1374&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
				<p><strong>WHSC NAS02</strong></p>
				<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1444&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
				<p><strong>WHSC NAS02</strong></p>
				<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?action=zoom&local_graph_id=1445&rra_id=0&graph_width=520&graph_height=100&graph_nolegend=true&graph_start=<?php echo strtotime("92 minutes ago"); ?>" />
			</div>
			
			<div class="graph-margin">
				<h1>Device status</h1>
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
			</div>
		</div>
		<div class="slide clearfix">
			<h1>Network Graphs</h1>
			<table cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						<h3>CURRIC &raquo; Router traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1374&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>WHSC-NAS02 traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1444&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>Wireless LAN controller</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1224&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
			</table>
		</div>
		<div class="slide clearfix">
			<h1>WHSC-SERVER01</h1>
			<table cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						<h3>Disk space C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1553&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk space E: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1557&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>Disk I/O C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1554&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk I/O E: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1555&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>CURRIC Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=71&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Hyper-V Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=26&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
			</table>
		</div>
		<div class="slide clearfix">
			<h1>WHSC-SERVER02</h1>
			<table cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						<h3>Disk space C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1559&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk space E: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1561&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>Disk I/O C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1560&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk I/O E: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1563&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>CURRIC Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=41&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Hyper-V Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=42&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
			</table>
		</div>
		<div class="slide clearfix">
			<h1>WHSC-SERVER03</h1>
			<table cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						<h3>Disk space C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1531&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk space E: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1532&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>Disk I/O C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1529&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk I/O E: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1538&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>CURRIC Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1535&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Hyper-V Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1536&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
			</table>
		</div>
		<div class="slide clearfix">
			<h1>WHSC-SERVER04</h1>
			<table cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						<h3>Disk space C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1543&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk space D: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1550&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>Disk I/O C: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1544&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>Disk I/O D: Drive</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1551&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<h3>CURRIC Traffic</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1549&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>RAM Usage</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1545&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
			</table>
		</div>
		<div class="slide clearfix">
			<h1>Syslog</h1>
			
			<table class="table border" cellspacing="0" cellpadding="0">
				<thead>
					<tr>
						<th>Time</th>
						<th>Message</th>
						<th>From</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($events as $row) : ?>
					<tr>
						<td><?php echo $row['time']; ?></td>
						<td><?php echo $row['Message']; ?></td>
						<td><?php echo $row['FromHost']; ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="slide clearfix">
			<h1>Printer toner status</h1>
			<table cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						<h3>E01</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1438&graph_height=150&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>E02</h3>
						
					</td>
				</tr>
				<tr>
					<td>
						<h3>E03</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1568&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
					<td>
						<h3>E Block Office</h3>
						<img src="http://www.whsc.vic.edu.au/cacti/graph_image.php?local_graph_id=1566&rra_id=0&view_type=tree&graph_start=<?php echo strtotime("4 hours ago"); ?>&graph_end=<?php echo time(); ?>" />
					</td>
				</tr>
			</table>
		</div>
	</div>
	<script>
		$(document).ready(function() {
			var divs = $("#slideshow > div").length; 
			var i = 1;
			
			$("#slideshow > div:gt(0)").hide();
			
			setInterval(function() { 
				$('#slideshow > div:first')
					.fadeOut()
					.next()
					.fadeIn()
					.end()
					.appendTo('#slideshow');
				
				if (i == divs) {
					location.reload(true);
				}
				
				i = i + 1;
				
			},  15000);
			
			if ($("#down > p").length > 1) {
				$("#down > p:gt(0)").hide();
				
				setInterval(function() { 
					$('#down > p:first')
						.fadeOut()
						.next()
						.delay(500)
						.fadeIn()
						.end()
						.appendTo('#down');
				},  5000);
			}
		});
	</script>
</body>
</html>