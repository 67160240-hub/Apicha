<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection
$DB_HOST = 'localhost';
$DB_USER = 's67160240';
$DB_PASS = 'd6kXZFSz';
$DB_NAME = 's67160240';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) { die('Database connection failed: ' . $mysqli->connect_error); }
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql){
    $res = $mysqli->query($sql); if(!$res) return [];
    $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; $res->free(); return $rows;
}

// --- Data for dashboard ---
$monthly = fetch_all($mysqli, "SELECT DATE_FORMAT(d.date_key,'%Y-%m') AS ym, SUM(f.net_amount) AS net_sales
    FROM fact_sales f JOIN dim_date d ON f.date_key=d.date_key GROUP BY ym ORDER BY ym");
$category = fetch_all($mysqli, "SELECT p.category, SUM(f.net_amount) AS net_sales
    FROM fact_sales f JOIN dim_product p ON f.product_id=p.product_id GROUP BY p.category");
$region = fetch_all($mysqli, "SELECT s.region, SUM(f.net_amount) AS net_sales
    FROM fact_sales f JOIN dim_store s ON f.store_id=s.store_id GROUP BY s.region");
$topProducts = fetch_all($mysqli, "SELECT p.product_name, SUM(f.quantity) AS qty_sold
    FROM fact_sales f JOIN dim_product p ON f.product_id=p.product_id GROUP BY p.product_id ORDER BY qty_sold DESC LIMIT 10");
$payment = fetch_all($mysqli, "SELECT payment_method, SUM(net_amount) AS net_sales FROM fact_sales GROUP BY payment_method");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, SUM(net_amount) AS net_sales FROM fact_sales GROUP BY hour_of_day ORDER BY hour_of_day");
$newReturning = fetch_all($mysqli, "SELECT d.date_key,
    SUM(CASE WHEN c.sign_up_date=d.date_key THEN f.net_amount ELSE 0 END) AS new_customer_sales,
    SUM(CASE WHEN c.sign_up_date<d.date_key THEN f.net_amount ELSE 0 END) AS returning_sales
    FROM fact_sales f JOIN dim_date d ON f.date_key=d.date_key JOIN dim_customer c ON f.customer_id=c.customer_id
    GROUP BY d.date_key ORDER BY d.date_key");
$kpis = fetch_all($mysqli, "SELECT
    SUM(CASE WHEN date_key>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) THEN net_amount ELSE 0 END) AS sales_30d,
    SUM(CASE WHEN date_key>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) THEN quantity ELSE 0 END) AS qty_30d,
    COUNT(DISTINCT CASE WHEN date_key>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) THEN customer_id END) AS buyers_30d
    FROM fact_sales");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];
function nf($n){return number_format((float)$n,2);}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Retail DW</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
body{background:#0f172a;color:#e2e8f0;font-family:sans-serif;}
.card{background:#111827;border-radius:1rem;border:1px solid rgba(255,255,255,.06);}
.card h5{color:#e5e7eb;}
.kpi{font-size:1.5rem;font-weight:700;}
.sub{color:#93c5fd;font-size:.9rem;}
.grid{display:grid;gap:1rem;grid-template-columns:repeat(12,1fr);}
.col-12{grid-column:span 12;}
.col-6{grid-column:span 6;}
.col-4{grid-column:span 4;}
.col-8{grid-column:span 8;}
@media(max-width:991px){.col-6,.col-4,.col-8{grid-column:span 12;}}
canvas{max-height:360px;}
</style>
</head>
<body class="p-3 p-md-4">
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">Dashboard Retail DW</h2>
        <span class="sub">ข้อมูลจาก MySQL</span>
    </div>
    <!-- KPI -->
    <div class="grid mb-3">
        <div class="card p-3 col-4"><h5>ยอดขาย 30 วัน</h5><div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div></div>
        <div class="card p-3 col-4"><h5>จำนวนชิ้นขาย 30 วัน</h5><div class="kpi"><?= number_format($kpi['qty_30d']) ?> ชิ้น</div></div>
        <div class="card p-3 col-4"><h5>จำนวนผู้ซื้อ 30 วัน</h5><div class="kpi"><?= number_format($kpi['buyers_30d']) ?> คน</div></div>
    </div>
    <!-- Charts -->
    <div class="grid">
        <div class="card p-3 col-8"><h5>ยอดขายรายเดือน</h5><canvas id="chartMonthly"></canvas></div>
        <div class="card p-3 col-4"><h5>สัดส่วนยอดขายตามหมวด</h5><canvas id="chartCategory"></canvas></div>
        <div class="card p-3 col-6"><h5>Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts"></canvas></div>
        <div class="card p-3 col-6"><h5>ยอดขายตามภูมิภาค</h5><canvas id="chartRegion"></canvas></div>
        <div class="card p-3 col-6"><h5>วิธีชำระเงิน</h5><canvas id="chartPayment"></canvas></div>
        <div class="card p-3 col-6"><h5>ยอดขายรายชั่วโมง</h5><canvas id="chartHourly"></canvas></div>
        <div class="card p-3 col-12"><h5>ลูกค้าใหม่ vs ลูกค้าเดิม</h5><canvas id="chartNewReturning"></canvas></div>
    </div>
</div>
<script>
const monthly=<?= json_encode($monthly,JSON_UNESCAPED_UNICODE) ?>;
const category=<?= json_encode($category,JSON_UNESCAPED_UNICODE) ?>;
const region=<?= json_encode($region,JSON_UNESCAPED_UNICODE) ?>;
const topProducts=<?= json_encode($topProducts,JSON_UNESCAPED_UNICODE) ?>;
const payment=<?= json_encode($payment,JSON_UNESCAPED_UNICODE) ?>;
const hourly=<?= json_encode($hourly,JSON_UNESCAPED_UNICODE) ?>;
const newReturning=<?= json_encode($newReturning,JSON_UNESCAPED_UNICODE) ?>;

const toXY=(arr,x,y)=>({labels:arr.map(o=>o[x]),values:arr.map(o=>parseFloat(o[y]||0))});

// Monthly chart
(()=>{
    const {labels,values}=toXY(monthly,'ym','net_sales');
    new Chart(document.getElementById('chartMonthly'),{
        type:'line',
        data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,tension:.25,fill:true,backgroundColor:'rgba(59,130,246,0.3)',borderColor:'#3b82f6'}]},
        options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}
    });
})();

// Category chart
(()=>{
    const {labels,values}=toXY(category,'category','net_sales');
    new Chart(document.getElementById('chartCategory'),{type:'doughnut',data:{labels,datasets:[{data:values,backgroundColor:['#3b82f6','#f97316','#14b8a6','#eab308','#8b5cf6']}]},options:{plugins:{legend:{position:'bottom',labels:{color:'#e5e7eb'}}}}});
})();

// Top Products
(()=>{
    const labels=topProducts.map(o=>o.product_name);
    const values=topProducts.map(o=>parseInt(o.qty_sold||0));
    new Chart(document.getElementById('chartTopProducts'),{type:'bar',data:{labels,datasets:[{label:'จำนวนชิ้น',data:values,backgroundColor:'#3b82f6'}]},options:{indexAxis:'y',plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}});
})();

// Region
(()=>{
    const {labels,values}=toXY(region,'region','net_sales');
    new Chart(document.getElementById('chartRegion'),{type:'bar',data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,backgroundColor:'#3b82f6'}]},options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}});
})();

// Payment
(()=>{
    const {labels,values}=toXY(payment,'payment_method','net_sales');
    new Chart(document.getElementById('chartPayment'),{type:'pie',data:{labels,datasets:[{data:values,backgroundColor:['#3b82f6','#f97316','#14b8a6','#eab308']}]},options:{plugins:{legend:{position:'bottom',labels:{color:'#e5e7eb'}}}}});
})();

// Hourly
(()=>{
    const {labels,values}=toXY(hourly,'hour_of_day','net_sales');
    new Chart(document.getElementById('chartHourly'),{type:'bar',data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,backgroundColor:'#3b82f6'}]},options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#c7d2fe'},grid:{color:'rgba(255,255,255,.08)'}}}}});
})();

// New vs Returning
(()=>{
    const labels=newReturning.map(o=>o.date_key);
    const newC=newReturning.map(o=>parseFloat(o.new_customer_sales||0));
    const retC=newReturning.map(o=>parseFloat(o.returning_sales||0));
    new Chart(document.getElementById('chartNewReturning'),{type:'line',data:{labels,datasets:[{label:'ลูกค้าใหม่ (฿)',data:newC,tension:.25,fill:false,borderColor:'#3b82f6'},{label:'ลูกค้าเดิม (฿)',data:retC,tension:.25,fill:false,borderColor:'#f97316'}]},options:{plugins:{legend:{labels:{color:'#e5e7eb'}}},scales:{x:{ticks:{color:'#c7d2fe'}},y:{ticks:{color:'#c7d2fe'}}}}});
})();
</script>
</body>
</html>
