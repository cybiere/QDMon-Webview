<?php 
$db = new SQLite3('qdmon.db', SQLITE3_OPEN_READWRITE);

if(isset($_GET['ack']) && !empty($_GET['ack']) && ctype_digit($_GET['ack'])){
	$statement = $db->prepare('UPDATE alerts SET ack = NOT ack WHERE id=:id');
	$statement->bindValue(':id',$_GET['ack'], SQLITE3_INTEGER);
	$result = $statement->execute();
}

$statement = $db->prepare('SELECT * FROM servers');
$result = $statement->execute();
$servers=[];
while(($row = $result->fetchArray(SQLITE3_ASSOC))){
	$servers[$row['name']] = [];
	$servers[$row['name']]['alerts'] = [];
	$servers[$row['name']]['alerts_ack'] = 0;
}
$result->finalize();

$statement = $db->prepare('SELECT metric FROM metrics GROUP BY metric');
$result = $statement->execute();
$metrics=[];
while(($row = $result->fetchArray(SQLITE3_ASSOC))){
	$metrics[] = substr($row['metric'],0,-6);
}
$result->finalize();

$statement = $db->prepare('SELECT server,metric,value,checkTime FROM metrics ORDER BY checktime');
$result = $statement->execute();
while(($row = $result->fetchArray(SQLITE3_ASSOC))){
	$metric = substr($row['metric'],0,-6);
	$servers[$row['server']][$metric] = $row['value'];
	$servers[$row['server']]['metrics'][$metric][$row['checkTime']] = $row['value'];
}
$result->finalize();

$statement = $db->prepare('SELECT * FROM alerts');
$result = $statement->execute();
while(($row = $result->fetchArray(SQLITE3_ASSOC))){
	$servers[$row['server']]['alerts'][] = [
		"checkpoint" => $row['checkpoint'],
		"message" => $row['message'],
		"time" => $row['checkTime'],
		"ack" => $row['ack'],
		"id" => $row['id']
	];
	$servers[$row['server']]['alerts_ack'] += $row['ack'];
}
$result->finalize();

?>

<!doctype html>
<html>
<head>
<title>QDMon web view</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="utf-8" />
<link rel="stylesheet" href="/css/bootstrap.min.css">
<style>
.graph{
	max-width: 150px;
}
.linkTd{
	overflow: hidden;
}
.linkTd a{
	display: block;
    margin: -10em;
    padding: 10em;
}
</style>
</head>
<body class="bg-dark">
<table class="table table-sm table-hover table-dark">
<thead class="thead-dark">
<tr>
<th>Name</th>
<?php
$nbcol = 2;
foreach($metrics as $metric){
	$nbcol +=2;
	echo "<th colspan='2'>$metric</th>\n";
}
?>
<th>Alerts</th>
</tr>
</thead>
<?php
foreach($servers as $name=>$server){
	echo "<tbody>\n";
	echo "<tr";
	echo ">\n";
	echo "<th";
	if(count($server['alerts'])-$server['alerts_ack'] != 0) echo " class='bg-danger'";
	echo ">".$name."</th>\n";
	foreach($metrics as $metric){
		echo "<td>".$server[$metric]."</td>\n";
		echo "<td class='graph'><canvas id='chart-".$name."-".$metric."'></canvas></td>\n";
	}
	echo "<td class='linkTd";
	if(count($server['alerts'])-$server['alerts_ack'] != 0) echo " bg-danger";
	echo "'>\n";
	if(count($server['alerts']) != 0){
		echo "<a href='#collapseAlert".$name."' data-toggle='collapse' class='text-white-50'>";
		echo "&#9662; ".count($server['alerts'])." alerts";
		if($server['alerts_ack'] != 0) echo " (".$server['alerts_ack']." ack)";
		echo "</a>";
	}else{
		echo "0 alerts";
	}
	echo "</td>\n";
	echo "</tr>\n";
	echo "</tbody>\n";
	if(count($server['alerts']) != 0){
		echo "<tbody class='collapse' id='collapseAlert".$name."'>\n";
		echo "<tr class='table-active'><th>&#9492; Check point</th><th colspan='".($nbcol-3)."'>Message</th><th>Time</th><th>Acquitted</th></tr>\n";
		foreach($server['alerts'] as $alert){
			echo "<tr";
			if($alert['ack'] != 0) echo " class='text-white-50'";
			echo ">\n";
			echo "<td>".$alert['checkpoint']."</td>\n";
			echo "<td colspan='".($nbcol-3)."'>".$alert['message']."</td>\n";
			echo "<td>".$alert['time']."</td>\n";
			echo "<td class='linkTd'><a href='/?ack=".$alert['id']."'>";
			echo $alert['ack'] == 0?"False":"True";
			echo "</a></td>\n";
			echo "</tr>\n";
		}
		echo "</tbody>\n";
	}
}
?>
</table>
</body>
<script src="/js/jquery-3.3.1.min.js"></script>
<script src="/js/chart.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script>
var chartId = []
var data = []
var labels = []
<?php
foreach($servers as $name=>$server){
	foreach($metrics as $metric){
?>			
id = '<?php echo $name."-".$metric; ?>'
chartId.push(id)
<?php
		$data = "[";
		$labels = "[";
		foreach($servers[$name]['metrics'][$metric] as $time=>$value){
			$data .= $value.",";
			$labels .= "'".$time."',";
		}
		$data .= "]";
		$labels .= "]";
?>
data[id] = <?php echo $data; ?>;
labels[id] = <?php echo $labels; ?>;
<?php
	} 
}
?>

chartId.forEach(function(id){
	ctx = document.getElementById("chart-"+id);
	config = {
		type: 'line',
		data: {
			labels: labels[id],
			datasets: [{
				backgroundColor: "#4A66A7",
				borderColor: "#315098",
				data: data[id],
				fill: true,
			}],
		},
		options: {
			legend :{
				display: false
			},
			responsive: true,
			title: {
				display: false
			},
			tooltips: {
				mode: 'index',
				position: 'nearest',
				intersect: false,
			},
			scales: {
				xAxes: [{
					display: true,
					type:'time',
					ticks:{
						maxRotation: 0,
					},
				}],
				yAxes: [{
					display: true,
					ticks:{
	                    beginAtZero:true
					}
				}]
			}
		}
	};
	window.myLine = new Chart(ctx, config);
});
</script>

</html>

