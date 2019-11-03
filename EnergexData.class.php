<?php
class EnergexData {
    private $data;
    private $usageCharge; //in cents
    private $dailySupplyCharge; //in cents
    private $discount; //in whole percentages (e.g. 11% would be 11)
    private $solarFeedInTariff; //in cents
    public function __construct($filename,$dailySupplyCharge,$usageCharge,$discount,$solarFeedInTariff) {                
        $this->data = $this->getCSV($filename);        

        //set fees
        $this->discount = $discount / 100;
        $this->dailySupplyCharge = ($dailySupplyCharge / 100) - (($dailySupplyCharge / 100) * $this->discount);
        $this->usageCharge = ($usageCharge / 100) - (($usageCharge / 100) * $this->discount);        
        $this->solarFeedInTariff = $solarFeedInTariff / 100;        
    }

    public function getProviderCharges() {
        return array(
            "daily_supply" => $this->dailySupplyCharge,
            "usage" => $this->usageCharge,
            "solar_feed_in_tariff" => $this->solarFeedInTariff,
            "discount" => $this->discount,
        );
    }

    public function getCSV($filename) {
        $data = array_map('str_getcsv', file($filename));
        return $data;
    }

    public function getData() {
        return $this->data;
    }

    public function getDataForIndex($index,$type) {
        if ($index==-1) {
            return false;
        }

        $dataStr = "";
        $labelStr = "";
        $runningTotal = 0;
        $counter = 1;    
        $loop = 0;
        $total = 0;

        foreach ($this->data[$index] as $currentData) {
            if ($loop>=2) {        
                if (!is_float($currentData) && !is_numeric($currentData)) {
                    break;
                }
                
                $runningTotal += $currentData;
                $total += $currentData;
            
                if ($counter && $counter % 4 == 0) {
                    $labelStr .= (($counter/4)-1).",";
                    $dataStr .= $runningTotal.",";    
                    $runningTotal = 0;
                }   
                
                $counter++;
            }
            $loop++;
        }

        if ($type == "electricity") {
            $charges = $this->dailySupplyCharge + ($this->usageCharge * $total);
        } else if ($type == "solar") {
            $charges = $this->solarFeedInTariff * $total;
        }
    
        $dataStr = rtrim($dataStr,",");
        $labelStr = rtrim($labelStr,",");
        return array(
            "labels" => $labelStr,
            "data" => $dataStr,
            "total" => $total,
            "charges" => $charges
        );
    }

    public function lookupDate($date) {                
        $count = 0;
        $solarIndex = -1;
        $electricityIndex = -1;
        foreach ($this->data as $i=>$data) {
            if ($data[0] == "300" && $data[1] == $date) {
                if (!$count) {
                    $solarIndex = $i;
                    $electricityIndex = $i;
                } else {
                    $electricityIndex = $i;
                }
                $count++;                
            }
        }
        
        $returnData = array();
        $returnData["electricity"] = $this->getDataForIndex($electricityIndex,"electricity");
        if ($electricityIndex != $solarIndex) { //then no solar 
            $returnData["solar"] = $this->getDataForIndex($solarIndex,"solar");
        }
        return $returnData;       
    }

    public function getPeriod($startDate, $endDate) {
        $start = (int)strtotime($startDate);
        $end = (int)strtotime($endDate);
        $electricityCharges = 0;
        $totalElectricityConsumption = 0;
        $totalSolarFeedIn = 0;
        $solarCharges = 0;
        $days = 0;                
        while ($start <= $end) {
            $currentDate = date("Ymd",$start);            
            $data = $this->lookupDate($currentDate);
            $electricityCharges += $data["electricity"]["charges"]; 
            $totalElectricityConsumption += $data["electricity"]["total"];
            $totalSolarFeedIn += (isset($data["solar"])?$data["solar"]["total"]:0);
            $solarCharges += (isset($data["solar"])?$data["solar"]["charges"]:0);
            $start += 86400;
            $days++;
        }

        $averageUsage = $totalElectricityConsumption / $days;
        $averageFeedIn = $totalSolarFeedIn / $days;

        $payment = ($electricityCharges - $solarCharges);
        $quarterly = ($payment / $days) * 90;
        return array(            
            "start_date" => $startDate,
            "end_date" => $endDate,
            "total_days" => $days,
            "electricity_charges" => $electricityCharges,
            "electricity_kw_total" => $totalElectricityConsumption,            
            "solar_feed_in_tariff" => $solarCharges,
            "solar_feed_in_kw_total" => $totalSolarFeedIn,
            "average_daily_kw_usage" => $averageUsage,
            "average_daily_solar_feed_in_kw" => $averageFeedIn,
            "payment" => $payment,
            "payment_type" => (($payment > 0)?"payment":"credit"),
            "estimated_quarterly_bill" => $quarterly
        );
    }
}
?>
