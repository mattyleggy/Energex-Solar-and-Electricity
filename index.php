<?php
ini_set('display_errors', '1');
require_once("EnergexData.class.php");

$csv = $_GET["csv"];

// Energy Australia
$supplyCharge = 99;
$usageCharge = 26.275;
$discount = 11;
$feedInTariff = 16.1;

// Origin
// $supplyCharge = 124.003;
// $usageCharge = 26.62;
// $discount = 21;
// $feedInTariff = 7;
$energexData = new EnergexData($csv,$supplyCharge,$usageCharge,$discount,$feedInTariff);
$data = $energexData->getData();

$date = $_GET["date"];

$data = $energexData->lookupDate($date);
$electricity = $data["electricity"]["data"];
$labelStr = $data["electricity"]["labels"];
$solar = "";
if (array_key_exists("solar",$data)) {
    $solar = $data["solar"]["data"];
}

$charges = $energexData->getPeriod(20190828,20191016);
// echo "<pre style='position: absolute;'>";
// print_r($charges);
// echo "</pre>";
?>
<!DOCTYPE html>
<html>
<head>
    <style>
    * { box-sizing: border-box; }
    body { padding: 20px; margin: 0px; font-family: sans-serif; font-size: 15px; background: #eee;  }
    body,html { height: 100%; width: 100%; }
    #page-wrapper,#page-wrapper > div#chart-wrapper { display: flex; height: 100%; width: 100%; align-items: center; flex-direction: row; justify-content: space-between; text-align: center; flex-wrap: wrap;  }
    #page-wrapper > div#chart-wrapper { height: auto; border: 0px; }
    #page-wrapper > div:not(#chart-wrapper),#page-wrapper > div#chart-wrapper > div { width: 24%; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24); }
    #page-wrapper > div#chart-wrapper > div {  }
    
    #page-wrapper > div#chart-wrapper > div#chart-box { display: block; width: 49.3%; min-height: 280px;}

    .box-content { padding: 20px; background: #fff; border-bottom-right-radius: 5px; border-bottom-left-radius: 5px; }
    .bold { font-weight: bold; }

    .box-heading { display: flex; justify-content: space-between; width: 100%; background: #ccc; padding: 8px 15px; font-weight: bold; border-top-right-radius: 5px; border-top-left-radius: 5px; }
    .electricity .box-heading { background: #dfaf67; }
    .solar .box-heading { background: #417030; color: #fff;  }    
    .box-heading a,.box-heading a:visited,.box-heading a:link { display: flex; justify-content: center; align-items: center; width: 20px; height: 20px; background: rgba(0,0,0,0.2); text-decoration: none; color: #000; }
    </style>
    <script src="chart.js"></script>
</head>
<body>    
    <div id="page-wrapper">
        <div class="solar">
            <div class="box-heading">Solar Generated to Grid</div>
            <div class="box-content"><?php echo $charges["solar_feed_in_kw_total"]." kW"; ?></div>                   
        </div>
        <div>
            <div class="box-heading">Current Bill</div>
            <div class="box-content"><?php echo (date("l, d F Y",strtotime($date))); ?></div>        
        </div>
        <div>
            <div class="box-heading">Date Range</div>
            <div class="box-content"><?php echo (date("d F Y",strtotime($charges["start_date"])))." - ".(date("d F Y",strtotime($charges["end_date"])))." (".$charges["total_days"]." days)";?></div>        
        </div>
        <div class="electricity">
            <div class="box-heading">Electricity Used</div>
            <div class="box-content"><?php echo $charges["electricity_kw_total"]." kW"; ?></div>                   
        </div> 
        <div id="chart-wrapper">
            <div class="solar">
                <div class="box-heading">Daily Average Solar Generated</div>
                <div class="box-content"><?php echo $charges["average_daily_solar_feed_in_kw"]; ?> kW</div>        
            </div>
            <div id="chart-box">    
                <div class="box-heading"><a href="<?php echo "?csv=".$_GET['csv']."&date=".date("Ymd",strtotime($date)-86400); ?>"><</a><span><?php echo (date("l, d F Y",strtotime($date))); ?></span><a href="<?php echo "?csv=".$_GET['csv']."&date=".date("Ymd",strtotime($date)+86400); ?>">></a></div>                   
                <div class="box-content">
                    <canvas id="myChart"></canvas>
                </div>  
            </div>
            <div class="electricity">
                <div class="box-heading">Daily Average Electricity Usage</div>
                <div class="box-content"><?php echo $charges["average_daily_kw_usage"]; ?> kW</div>        
            </div>
        </div>  
        <div class="solar">
            <div class="box-heading">Solar Credit</div>
            <div class="box-content">$<?php echo $charges["solar_feed_in_tariff"]; ?></div>        
        </div>
        <div>
            <div class="box-heading">Bill to date (<?php echo $charges["total_days"]; ?> days)</div>
            <div class="box-content">$<?php echo (float)$charges["payment"]." ".$charges["payment_type"]; ?></div>        
        </div>    
        <div>
            <div class="box-heading">Estimated Quarterly Bill (90 days)</div>
            <div class="box-content">$<?php echo $charges["estimated_quarterly_bill"]; ?></div>        
        </div>
        <div class="electricity">
            <div class="box-heading">Electricity Charges</div>
            <div class="box-content">$<?php echo $charges["electricity_charges"]; ?></div>        
        </div>
    </div>
    <script>
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',        
            data: {
                labels: [<?php echo $labelStr; ?>],
                datasets: [
                <?php if (isset($data["solar"])) { ?>
                {
                    label: 'Solar Generation (<?php echo $data["solar"]["total"]; ?>kW) - $<?php echo number_format($data["solar"]["charges"],2); ?> credit',
                    data: [<?php echo $solar; ?>],
                    backgroundColor: '#417030',
                    borderColor: '#417030',
                    borderWidth: 1
                },
                <?php } ?>
                {
                    label: 'Electricity (<?php echo $data["electricity"]["total"]; ?>kW) - $<?php echo number_format($data["electricity"]["charges"],2); ?> charge',
                    data: [<?php echo $electricity; ?>],
                    backgroundColor: '#dfaf67',
                    borderColor: '#dfaf67',
                    borderWidth: 1
                }]
            },
            options: {
                legend: {
                    position: 'bottom'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
        </script>
    </body>