<?php
	/**
	 * Status overview
	 * @author Michael Greenhill
	 */
	
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
		array('server_id', 'ip', 'port', 'label', 'type', 'status', 'active', 'last_online', 'last_check', 'rtime', 'rtime_max'),
		'',
		array('label')
	);
	
	$updater 	= new smUpdaterStatus();
	$offline 	= array(); 
	$online		= array(); 
	
	$colours['offline']['background'] 	= "#a00000";
	$colours['offline']['foreground'] 	= "#f7cece";
	$colours['online']['background']	= "#53a000";
	$colours['online']['foreground']	= "#d8f7ce";
	$colours['slow']['background']		= "#f0e925";
	$colours['slow']['foreground']		= "#8a770f";
	
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
		background: #000;
		color: #fff;
		padding: 10px; 
		margin: 0;
		font-family: Verdana, Geneva, sans-serif;
		font-size: 10pt;
		padding-right: 0;
	}
	
	h2, p { 
		margin: 0;
		padding: 0;
	}
	
	h2 {
		margin-bottom: 10px;
	}
	
	#offline {
		float: left; width: 310px; 
	}
	
		#offline .entity {
			background: <?php echo $colours['offline']['background']; ?>;
			color: <?php echo $colours['offline']['foreground']; ?>;
			border: 2px solid <?php echo $colours['offline']['foreground']; ?>; 
			border-radius: 3px;
			margin-bottom: 10px;
			padding: 10px;
			box-shadow: 0px 0px 5px #666;
		}
		
	#online {
		margin-left: 310px;
	}
	
		#online .entity {
			background: <?php echo $colours['online']['background']; ?>;
			color: <?php echo $colours['online']['foreground']; ?>;
			border: 2px solid <?php echo $colours['online']['foreground']; ?>; 
			border-radius: 3px;
			margin-bottom: 10px;
			padding: 10px;
			box-shadow: 0px 0px 5px #666;
			float: left;
			margin-left: 10px;
			width: 280px;
		}
	
		#online .entity_slow {
			background: <?php echo $colours['slow']['background']; ?>;
			color: <?php echo $colours['slow']['foreground']; ?>;
			border: 2px solid <?php echo $colours['slow']['foreground']; ?>; 
			border-radius: 3px;
			margin-bottom: 10px;
			padding: 10px;
			box-shadow: 0px 0px 5px #666;
			float: left;
			margin-left: 10px;
			width: 280px;
		}
	ul {
		margin: 0; padding: 0;
		list-style: none;
	}
	
	li {
		display: none;
		font-size: 130%; 
	}
</style>
</head>

<body>
	<div class="clearfix">
		<div id="offline">
			<?php foreach ($offline as $id => $host) : ?>
			<div class="entity">
				<h2><?php echo $host['label']; ?></h2>
				<p>Offline since <?php echo $host['last_online']; ?></p>
				<p>Last checked <?php echo $host['last_checked_nice']; ?></p>
			</div>
			<?php endforeach; ?>
			<?php if (count($offline) == 0) : ?>
			<h2>All services online</h2>
			<?php endif; ?>
		</div>
		<div id="online">
			<?php foreach ($online as $id => $host) : ?>
			<div class="<?php if ($host['rtime'] >= $host['rtime_max']) : ?>entity_slow<?php else: ?>entity<?php endif; ?>">
				<h2><?php echo $host['label']; ?></h2>
				<p>Response time: <?php echo round($host['rtime'], 4); ?>s</p>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<h2>Latest syslog entries</h2>
	<ul id="syslog" style="min-height: 50px;">
	<?php foreach ($events as $id => $data) : ?>
		<li><strong><?php echo $data['FromHost']; ?></strong> - <?php echo $data['Message'] ." (".$data['time'].")"; ?></li>
	<?php endforeach ; ?>
	</ul>
	
	<?php if (isset($_GET['large']) && $_GET['large'] == true) : ?>
	<h2>Graphs</h2>
	<img src="http://broadband.doe.wan/common/graph_unified.cgi?profile=mrtg_new&routerid=29808&hsize=690&vsize=200&period=1h&notitle">
	<?php endif; ?>
	
	<script type="text/javascript" src="jquery-1.7.2.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
            var timeOuts = new Array();
            var eT = 5000;
            
			function myFadeIn(jqObj) {
				$(".current").fadeOut('fast').delay(50);
                jqObj.delay(300).fadeIn();
				jqObj.addClass("current");
            }
			
            function clearAllTimeouts() {
                for (key in timeOuts) {
                    clearTimeout(timeOuts[key]);
                }
            }
			
			function hideAll() {
				$('#syslog li').hide().each(function(index) {
					timeOuts[index] = setTimeout(myFadeIn, index*eT, $(this));
				});
			}
			
			hideAll();
			function showEvents() {
				clearAllTimeouts();
                $('#syslog li').stop(true,true).hide();
                $('#syslog li').each(function(index) {
                    timeOuts[index] = setTimeout(myFadeIn, index*eT, $(this));
                });
			}
            
			$(window).ready(function() {
				showEvents(function() {
					document.location.reload(true);
				});
			});
        });
	</script>
</body>
</html>
