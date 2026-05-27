<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');
$host="localhost";
$user="root";
$password="";
$database="freshmart";

$conn = new mysqli($host,$user,$password,$database);
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

if($_SERVER['REQUEST_METHOD'] == "POST"){
$data = json_decode(file_get_contents("php://input"), true);

    if(isset($data['name']) && isset($data['actual'])){
        $name = $data['name'];
        $price = $data['actual'];
        $qty = $data['qty'];
        $unit = $data['unit'] ?? 'pcs';
        $gst = $data['gst'] ?? 0;
        $fresh = $data['fresh'] ?? 0;
        $packSize = $data['packSize'] ?? '';
        $category = $data['category'] ?? '';
        $brand = $data['brand'] ?? '';

        $sql = "INSERT INTO inventory(product_name, quantity, price, unit, gst, fresh_price, pack_size, category, brand)
                VALUES ('$name','$qty','$price','$unit','$gst','$fresh','$packSize','$category','$brand')";
        if($conn->query($sql)){
            echo "inventory saved";
        } else {
            echo $conn->error;
        }
        exit;
    }

    if(isset($data['action']) && $data['action']=="addStock"){
        $id = $data['id'];
        $qty = $data['qty'];
        $conn->query("UPDATE inventory SET quantity = quantity + $qty WHERE id=$id");
        echo "Stock Updated";
        exit;
    }

    if(isset($data['action']) && $data['action']=="update_inventory"){
        $id = $data['id'];
        $name = $data['name'];
        $addStock = $data['addStock'];
        $conn->query("UPDATE inventory SET product_name='$name', quantity = quantity + $addStock WHERE id='$id'");
        echo "Inventory Updated";
        exit;
    }

    if(isset($data['action']) && $data['action']=="delete_inventory"){
        $id = $data['id'];
        if($conn->query("DELETE FROM inventory WHERE id='$id'")){
            echo json_encode(["status"=>"success"]);
        }else{
            echo json_encode(["status"=>"error"]);
        }
        exit;
    }

if(isset($data['items']) && isset($data['paymentMethod']) && isset($data['total'])){
    $total = $data['total'];
    $paymentMethod = $data['paymentMethod'];
    $paymentRef = $data['paymentRef'] ?? '';
    $customerName = $data['customerName'] ?? '';
    $billId = $data['billId'] ?? ('BILL'.time());
    $date = date("Y-m-d");
    $time = date("H:i:s");

    $sqlPayment = "INSERT INTO payment 
    (bill_id, amount, payment_method, payment_ref, customer_name, date, time)
    VALUES 
    ('$billId','$total','$paymentMethod','$paymentRef','$customerName','$date','$time')";
    $conn->query($sqlPayment);

    foreach($data['items'] as $item){
        $name = $item['name'];
        $price = $item['actual'];
        $gst = $item['gst'];
        $fresh = $item['fresh'];
        $qty = $item['qty'];
        $unit = $item['unit'] ?? 'pcs';
        $brand = $item['brand'] ?? '';
        $pack = $item['pack'] ?? '';
        $conn->query("INSERT INTO sales
        (bill_id, product_name, actual_price, gst, fresh_price, qty, unit, brand, pack_size, total, date, time)
        VALUES 
        ('$billId','$name','$price','$gst','$fresh','$qty','$unit','$brand','$pack','$total','$date','$time')");
    }
    echo "Saved Successfully";
    exit;
}
}

if(isset($_GET['action'])){
    if($_GET['action']=="get_inventory"){
        $result = $conn->query("SELECT * FROM inventory");
        $rows = [];
        while($row = $result->fetch_assoc()) { $rows[] = $row; }
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }
if($_GET['action']=="get_sales"){
    $result = $conn->query("SELECT s.*, p.payment_method, p.payment_ref, p.customer_name 
                            FROM sales s 
                            LEFT JOIN payment p ON s.bill_id = p.bill_id 
                            ORDER BY s.id DESC");
    if(!$result){
        echo json_encode(["error" => $conn->error]);
        exit;
    }
    $rows = [];
    while($row = $result->fetch_assoc()) { $rows[] = $row; }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

    if($_GET['action'] == 'get_payment'){
        $res = $conn->query("SELECT p.*, 
            GROUP_CONCAT(CONCAT(s.product_name,'|',s.qty,'|',IFNULL(s.brand,''),'|',IFNULL(s.pack_size,'')) 
            SEPARATOR ';;') as items_list
            FROM payment p
            LEFT JOIN sales s ON p.bill_id = s.bill_id
            GROUP BY p.id
            ORDER BY p.id DESC");
        if(!$res){
            echo json_encode(["error" => $conn->error]);
            exit;
        }
        $rows = [];
        while($row = $res->fetch_assoc()){ $rows[] = $row; }
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FreshMart Grocery Manager Pro 2025</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
:root{
    --primary:#1a6b3a;
    --primary-dark:#0f4225;
    --primary-light:#27ae60;
    --accent:#f39c12;
    --accent2:#e74c3c;
    --accent3:#3498db;
    --accent4:#9b59b6;
    --accent5:#1abc9c;
    --success:#27ae60;
    --danger:#e74c3c;
    --warning:#f39c12;
    --light:#f0faf4;
    --white:#ffffff;
    --text:#1a2e1a;
    --text-light:#5a7a5a;
    --shadow:0 8px 32px rgba(26,107,58,0.15);
    --shadow-hover:0 16px 48px rgba(26,107,58,0.25);
    --radius:18px;
    --radius-sm:10px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Nunito',sans-serif;background:var(--light);color:var(--text);min-height:100vh;}
::-webkit-scrollbar{width:7px;}
::-webkit-scrollbar-track{background:#e8f5e9;}
::-webkit-scrollbar-thumb{background:var(--primary);border-radius:10px;}
button{padding:10px 20px;border:none;border-radius:var(--radius-sm);cursor:pointer;font-weight:700;font-family:'Nunito',sans-serif;font-size:14px;transition:all 0.25s ease;letter-spacing:0.3px;}
button:hover{transform:translateY(-2px);filter:brightness(1.08);}
button:active{transform:translateY(0);}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;box-shadow:0 4px 15px rgba(26,107,58,0.3);}
.btn-success{background:linear-gradient(135deg,#27ae60,#2ecc71);color:white;box-shadow:0 4px 15px rgba(39,174,96,0.3);}
.btn-danger{background:linear-gradient(135deg,#c0392b,#e74c3c);color:white;box-shadow:0 4px 15px rgba(231,76,60,0.3);}
.btn-warning{background:linear-gradient(135deg,#e67e22,#f39c12);color:white;box-shadow:0 4px 15px rgba(243,156,18,0.3);}
.btn-info{background:linear-gradient(135deg,#2980b9,#3498db);color:white;box-shadow:0 4px 15px rgba(52,152,219,0.3);}
.btn-purple{background:linear-gradient(135deg,#8e44ad,#9b59b6);color:white;box-shadow:0 4px 15px rgba(155,89,182,0.3);}
.btn-sm{padding:6px 14px;font-size:12px;border-radius:8px;}
input,select{padding:12px 16px;margin:6px 0;border:2px solid #d4edda;border-radius:var(--radius-sm);width:100%;font-family:'Nunito',sans-serif;font-size:14px;background:white;color:var(--text);transition:border 0.2s,box-shadow 0.2s;outline:none;}
input:focus,select:focus{border-color:var(--primary-light);box-shadow:0 0 0 3px rgba(39,174,96,0.15);}

/* LOGO */
.freshmart-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.freshmart-logo .logo-icon{width:44px;height:44px;background:linear-gradient(135deg,#27ae60,#f39c12);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;box-shadow:0 4px 12px rgba(39,174,96,0.4);flex-shrink:0;}
.freshmart-logo .logo-text{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;background:linear-gradient(135deg,#1a6b3a,#27ae60);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.freshmart-logo .logo-sub{font-size:10px;color:var(--accent);font-weight:700;letter-spacing:2px;text-transform:uppercase;-webkit-text-fill-color:var(--accent);}
.freshmart-logo-white .logo-text{background:linear-gradient(135deg,#ffffff,#a8e6bf);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.freshmart-logo-white .logo-sub{color:#f9ca24;-webkit-text-fill-color:#f9ca24;}

/* LOGIN */
#loginPage{min-height:100vh;display:flex;flex-direction:column;justify-content:center;align-items:center;position:relative;overflow:hidden;background:linear-gradient(135deg,#0a3d1f 0%,#1a6b3a 40%,#27ae60 70%,#82e0aa 100%);}
#loginPage::before{content:'';position:absolute;width:600px;height:600px;background:rgba(255,255,255,0.05);border-radius:50%;top:-200px;left:-200px;animation:float 8s ease-in-out infinite;}
#loginPage::after{content:'';position:absolute;width:400px;height:400px;background:rgba(255,255,255,0.05);border-radius:50%;bottom:-100px;right:-100px;animation:float 6s ease-in-out infinite reverse;}
.login-floating-icons{position:absolute;width:100%;height:100%;pointer-events:none;overflow:hidden;}
.float-icon{position:absolute;font-size:40px;opacity:0.15;animation:floatIcon 10s ease-in-out infinite;}
@keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-20px);}}
@keyframes floatIcon{0%,100%{transform:translateY(0) rotate(0deg);opacity:0.15;}50%{transform:translateY(-30px) rotate(10deg);opacity:0.25;}}
.login-card{background:rgba(255,255,255,0.97);backdrop-filter:blur(20px);padding:45px 40px;border-radius:28px;box-shadow:0 30px 80px rgba(0,0,0,0.3),0 0 0 1px rgba(255,255,255,0.2);width:380px;position:relative;z-index:10;animation:slideUp 0.6s ease;}
@keyframes slideUp{from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);}}
.login-card .logo-wrap{text-align:center;margin-bottom:30px;}
.login-card h2{text-align:center;font-size:15px;color:var(--text-light);margin-bottom:25px;font-weight:600;}
.login-input-group{position:relative;margin-bottom:5px;}
.login-input-group .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:18px;z-index:2;}
.login-input-group input{padding-left:44px;}
.login-btn{width:100%;padding:14px;font-size:16px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;border:none;border-radius:12px;cursor:pointer;font-weight:800;margin-top:10px;box-shadow:0 6px 20px rgba(26,107,58,0.4);transition:all 0.3s;letter-spacing:0.5px;}
.login-btn:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(26,107,58,0.5);}
.login-hint{background:linear-gradient(135deg,#e8f5e9,#f0fff4);border:1px solid #c8e6c9;border-radius:10px;padding:12px 15px;font-size:12px;color:var(--primary);margin-top:15px;text-align:center;}

/* WELCOME */
#freshmartPage{display:none;min-height:100vh;background:linear-gradient(135deg,#0a3d1f,#1a6b3a,#27ae60,#82e0aa);color:white;flex-direction:column;justify-content:center;align-items:center;text-align:center;position:relative;overflow:hidden;}
#freshmartPage::before{content:'🌿';position:absolute;font-size:300px;opacity:0.05;top:-50px;left:-50px;}
.welcome-content{position:relative;z-index:2;}
.welcome-content h1{font-family:'Playfair Display',serif;font-size:56px;font-weight:800;margin-bottom:10px;text-shadow:0 4px 20px rgba(0,0,0,0.3);}
.welcome-content p{font-size:18px;opacity:0.9;margin-bottom:35px;}
.enter-btn{padding:16px 45px;background:white;color:var(--primary);border:none;border-radius:50px;font-size:17px;font-weight:800;cursor:pointer;box-shadow:0 8px 30px rgba(0,0,0,0.2);transition:all 0.3s;font-family:'Nunito',sans-serif;}
.enter-btn:hover{transform:translateY(-4px) scale(1.03);box-shadow:0 15px 40px rgba(0,0,0,0.3);}
.welcome-stats{display:flex;gap:30px;margin-top:40px;flex-wrap:wrap;justify-content:center;}
.welcome-stat{background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);border-radius:16px;padding:20px 30px;text-align:center;}
.welcome-stat .stat-num{font-size:32px;font-weight:900;}
.welcome-stat .stat-label{font-size:12px;opacity:0.8;text-transform:uppercase;letter-spacing:1px;}

/* DASHBOARD */
#dashboard{display:none;min-height:100vh;background:linear-gradient(135deg,#0a3d1f 0%,#1a4d2e 50%,#0f3823 100%);padding:0;}
.dashboard-topbar{background:rgba(255,255,255,0.08);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,0.1);padding:15px 35px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;}
.dashboard-content{padding:35px;min-height:calc(100vh - 75px);}
.dashboard-hero{text-align:center;margin-bottom:40px;color:white;}
.dashboard-hero h1{font-family:'Playfair Display',serif;font-size:38px;font-weight:800;margin-bottom:5px;text-shadow:0 3px 15px rgba(0,0,0,0.3);}
.dashboard-hero p{font-size:15px;opacity:0.75;letter-spacing:0.5px;}
.dashboard-date{background:rgba(255,255,255,0.12);display:inline-block;padding:6px 18px;border-radius:20px;font-size:13px;color:rgba(255,255,255,0.85);margin-bottom:12px;border:1px solid rgba(255,255,255,0.15);}
.dashboard-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;max-width:1100px;margin:0 auto;}
.dash-card{background:white;border-radius:24px;padding:38px 28px;text-align:center;cursor:pointer;box-shadow:0 10px 40px rgba(0,0,0,0.15);transition:all 0.35s cubic-bezier(0.175,0.885,0.32,1.275);position:relative;overflow:hidden;}
.dash-card::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle,rgba(255,255,255,0.08) 0%,transparent 70%);opacity:0;transition:opacity 0.3s;}
.dash-card:hover::before{opacity:1;}
.dash-card:hover{transform:translateY(-10px) scale(1.03);box-shadow:0 25px 60px rgba(0,0,0,0.25);}
.dash-card:active{transform:translateY(-5px) scale(1.01);}
.dash-card .card-icon{font-size:60px;display:block;margin-bottom:18px;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.15));animation:iconBounce 3s ease-in-out infinite;}
@keyframes iconBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(-5px);}}
.dash-card .card-title{font-size:18px;font-weight:800;margin-bottom:6px;letter-spacing:0.3px;}
.dash-card .card-desc{font-size:12px;opacity:0.65;font-weight:600;text-transform:uppercase;letter-spacing:1px;}
.dash-card .card-badge{position:absolute;top:16px;right:16px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;color:white;letter-spacing:0.5px;}
.card-inventory{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border:2px solid #a5d6a7;}
.card-inventory .card-title{color:#1b5e20;}
.card-inventory .card-badge{background:#2e7d32;}
.card-billing{background:linear-gradient(135deg,#fff3e0,#ffe0b2);border:2px solid #ffcc02;}
.card-billing .card-title{color:#e65100;}
.card-billing .card-badge{background:#ef6c00;}
.card-addprod{background:linear-gradient(135deg,#e8eaf6,#c5cae9);border:2px solid #9fa8da;}
.card-addprod .card-title{color:#1a237e;}
.card-addprod .card-badge{background:#303f9f;}
.card-sales{background:linear-gradient(135deg,#fce4ec,#f8bbd0);border:2px solid #f48fb1;}
.card-sales .card-title{color:#880e4f;}
.card-sales .card-badge{background:#c2185b;}
.card-graph{background:linear-gradient(135deg,#e0f7fa,#b2ebf2);border:2px solid #80deea;}
.card-graph .card-title{color:#006064;}
.card-graph .card-badge{background:#00838f;}
.card-payment{background:linear-gradient(135deg,#f3e5f5,#e1bee7);border:2px solid #ce93d8;}
.card-payment .card-title{color:#4a148c;}
.card-payment .card-badge{background:#6a1b9a;}

/* PAGES */
.page{display:none;padding:0;}
.page.active{display:block;}
.page-topbar{background:white;padding:18px 30px;display:flex;align-items:center;gap:15px;box-shadow:0 2px 15px rgba(0,0,0,0.08);border-bottom:3px solid var(--primary-light);position:sticky;top:0;z-index:50;}
.page-topbar h2{font-family:'Playfair Display',serif;font-size:24px;color:var(--primary);flex:1;}
.page-body{padding:28px 35px;max-width:1200px;margin:0 auto;}
.card{background:white;padding:24px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:20px;}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid var(--light);}
.card-header h3{font-size:17px;font-weight:800;color:var(--primary);}

/* INVENTORY */
#inventory{background:#f0faf4;}
.category-card{background:white;border-radius:16px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,0.06);overflow:hidden;border:2px solid #e8f5e9;transition:box-shadow 0.3s;}
.category-card:hover{box-shadow:0 8px 25px rgba(0,0,0,0.1);}
.category-header{display:flex;align-items:center;gap:12px;padding:16px 22px;cursor:pointer;background:linear-gradient(135deg,#f0faf4,#e8f5e9);transition:background 0.2s;user-select:none;}
.category-header:hover{background:linear-gradient(135deg,#e8f5e9,#d4edda);}
.category-icon{font-size:28px;}
.category-name{font-size:18px;font-weight:800;color:var(--primary);flex:1;}
.category-count{background:var(--primary);color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;}
.search-bar{position:relative;margin-bottom:20px;}
.search-bar input{padding-left:44px;background:white;border:2px solid #c8e6c9;border-radius:12px;}
.search-bar::before{content:'🔍';position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:16px;z-index:2;pointer-events:none;}
.stock-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-left:8px;}
.stock-ok{background:#e8f5e9;color:#1b5e20;}
.stock-low{background:#fff3e0;color:#e65100;}
.stock-out{background:#ffebee;color:#b71c1c;}

/* CATEGORY DETAIL */
#categoryDetail{background:#f0faf4;}
.cat-detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;}
.cat-product-card{background:white;border-radius:16px;padding:18px;box-shadow:0 4px 15px rgba(0,0,0,0.07);border:2px solid #e8f5e9;transition:all 0.2s;}
.cat-product-card:hover{box-shadow:0 8px 25px rgba(0,0,0,0.12);transform:translateY(-2px);}
.cat-product-name{font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px;}
.cat-product-brand{font-size:13px;font-weight:700;color:var(--primary);margin-bottom:10px;}
.cat-product-details{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
.cat-detail-chip{background:#f0faf4;border-radius:8px;padding:8px 10px;font-size:12px;font-weight:700;}
.cat-detail-chip .chip-label{color:var(--text-light);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:2px;}
.cat-detail-chip .chip-value{color:var(--text);font-size:13px;}
.cat-product-actions{display:flex;gap:8px;margin-top:12px;}
.cat-product-stock-bar{height:6px;background:#e8f5e9;border-radius:10px;margin-top:10px;overflow:hidden;}
.cat-product-stock-fill{height:100%;background:var(--primary-light);border-radius:10px;transition:width 0.3s;}

/* BILLING */
#billing{background:#fffbf0;}
.billing-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:24px;align-items:start;}
.cart-item{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:linear-gradient(135deg,#fffbf0,#fff3e0);margin-bottom:8px;border-radius:12px;border:1px solid #ffe0b2;}
.cart-item-name{font-weight:700;font-size:14px;}
.cart-item-price{color:var(--accent);font-weight:800;font-size:16px;}
.total-box{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;padding:20px;border-radius:16px;text-align:center;margin:15px 0;}
.total-box .total-label{font-size:13px;opacity:0.8;text-transform:uppercase;letter-spacing:1px;}
.total-box .total-amount{font-size:36px;font-weight:900;font-family:'Playfair Display',serif;}
.payment-methods{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:12px 0;}
.payment-btn{padding:14px 8px;border:2px solid #e0e0e0;border-radius:12px;background:white;cursor:pointer;text-align:center;font-size:13px;font-weight:700;transition:all 0.2s;color:var(--text);display:flex;flex-direction:column;align-items:center;gap:6px;}
.payment-btn .pay-icon{font-size:24px;}
.payment-btn:hover,.payment-btn.active{border-color:var(--primary);background:linear-gradient(135deg,#e8f5e9,#c8e6c9);color:var(--primary);transform:translateY(-2px);box-shadow:0 4px 12px rgba(26,107,58,0.2);}
.payment-ref-box{background:linear-gradient(135deg,#e3f2fd,#bbdefb);border:2px solid #90caf9;border-radius:12px;padding:14px;margin-top:10px;display:none;}
.payment-ref-box.show{display:block;}
.upi-apps{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;}
.upi-app-btn{padding:8px 14px;border:2px solid #e0e0e0;border-radius:8px;background:white;cursor:pointer;font-size:13px;font-weight:700;transition:all 0.2s;}
.upi-app-btn:hover,.upi-app-btn.selected{border-color:#9b59b6;background:linear-gradient(135deg,#f3e5f5,#e1bee7);color:#6a1b9a;}
.card-type-btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;}

/* PRODUCT SEARCH DROPDOWN */
.product-search-wrap{position:relative;}
.product-search-wrap input{width:100%;padding-right:36px;}
.product-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:white;border:2px solid #c8e6c9;border-radius:12px;max-height:260px;overflow-y:auto;z-index:200;box-shadow:0 8px 30px rgba(26,107,58,0.15);display:none;}
.product-dropdown.open{display:block;}
.product-dropdown-item{padding:12px 16px;cursor:pointer;border-bottom:1px solid #f0f0f0;transition:background 0.15s;display:flex;flex-direction:column;gap:2px;}
.product-dropdown-item:last-child{border-bottom:none;}
.product-dropdown-item:hover,.product-dropdown-item.focused{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);}
.product-dropdown-item .pd-name{font-weight:800;font-size:14px;color:var(--text);}
.product-dropdown-item .pd-meta{font-size:12px;color:var(--text-light);}
.product-dropdown-item .pd-price{font-size:13px;font-weight:700;color:var(--primary);}
.product-dropdown-empty{padding:18px 16px;text-align:center;color:var(--text-light);font-size:13px;}

/* ===== ADD PRODUCT PAGE - UPDATED ===== */
#addProduct{background:#f0f0ff;}
.add-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:4px;}
.form-group label{font-size:13px;font-weight:700;color:var(--primary);margin-left:2px;}
.form-group.full{grid-column:1/-1;}

/* GST autocomplete hint */
.gst-hint{
    font-size:11px;color:var(--primary);font-weight:700;
    background:#e8f5e9;padding:4px 10px;border-radius:8px;
    margin-top:2px;display:none;
}
.gst-hint.show{display:block;}

/* SALES */
#sales{background:#fff5f5;}
.sales-filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;background:white;padding:18px;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,0.06);margin-bottom:20px;border:2px solid #fce4ec;}
.filter-group{display:flex;flex-direction:column;gap:4px;flex:1;min-width:150px;}
.filter-group label{font-size:12px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;}
.filter-group select,.filter-group input{margin:0;padding:10px 12px;}
.sales-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}
.summary-card{background:white;border-radius:16px;padding:20px;text-align:center;box-shadow:0 4px 15px rgba(0,0,0,0.06);}
.summary-card .sum-icon{font-size:30px;margin-bottom:8px;}
.summary-card .sum-value{font-size:22px;font-weight:900;color:var(--primary);}
.summary-card .sum-label{font-size:11px;color:var(--text-light);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
.sale-record{background:white;border-radius:14px;padding:18px;margin-bottom:12px;box-shadow:0 3px 12px rgba(0,0,0,0.06);border-left:5px solid var(--primary);transition:all 0.2s;}
.sale-record:hover{box-shadow:0 6px 20px rgba(0,0,0,0.1);transform:translateX(3px);}
.sale-record-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f0f0f0;}
.sale-date{font-size:13px;color:var(--text-light);}
.sale-total{font-size:18px;font-weight:900;color:var(--primary);}
.payment-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-left:10px;}
.pay-cash{background:#e8f5e9;color:#1b5e20;}
.pay-card{background:#e3f2fd;color:#0d47a1;}
.pay-upi{background:#f3e5f5;color:#4a148c;}
.sale-item-detail{font-size:13px;color:var(--text-light);padding:6px 10px;background:#fafafa;border-radius:8px;margin-top:5px;border-left:2px solid var(--primary-light);}

/* PAYMENT HISTORY */
#paymentHistory{background:#f5f0ff;}
.payment-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.pay-stat-card{border-radius:16px;padding:24px;text-align:center;color:white;box-shadow:0 6px 20px rgba(0,0,0,0.12);}
.pay-stat-cash{background:linear-gradient(135deg,#1b5e20,#27ae60);}
.pay-stat-card2{background:linear-gradient(135deg,#0d47a1,#1976d2);}
.pay-stat-upi{background:linear-gradient(135deg,#4a148c,#7b1fa2);}
.pay-stat-card .stat-icon{font-size:36px;margin-bottom:10px;}
.pay-stat-card .stat-amount{font-size:28px;font-weight:900;}
.pay-stat-card .stat-count{font-size:13px;opacity:0.85;margin-top:4px;}
.pay-stat-card .stat-label{font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
.payment-table{background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.08);}
.payment-table table{width:100%;border-collapse:collapse;}
.payment-table th{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;padding:14px 18px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;}
.payment-table td{padding:14px 18px;border-bottom:1px solid #f0f0f0;font-size:14px;}
.payment-table tr:last-child td{border-bottom:none;}
.payment-table tr:hover td{background:#f9fffe;}

/* SALES GRAPH */
#salesGraph{background:#f0f8ff;}
.graph-tabs{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.graph-tab{padding:10px 24px;border:2px solid var(--primary);border-radius:30px;cursor:pointer;background:white;font-weight:700;font-family:'Nunito',sans-serif;transition:all 0.25s;font-size:13px;}
.graph-tab.active,.graph-tab:hover{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;border-color:transparent;box-shadow:0 4px 15px rgba(26,107,58,0.3);}
.chart-container{background:white;padding:24px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:22px;}
.chart-container h3{font-size:16px;font-weight:800;color:var(--primary);margin-bottom:16px;}
.top-product-item{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#f9fffe;margin-bottom:8px;border-radius:12px;border-left:4px solid var(--success);transition:all 0.2s;}
.top-product-item:hover{background:#e8f5e9;transform:translateX(4px);}
.top-product-rank{font-size:24px;font-weight:900;color:var(--primary);margin-right:15px;}
.top-product-name{font-weight:700;font-size:15px;}
.top-product-stats{color:#666;font-size:13px;}
.top-product-qty{font-size:18px;font-weight:900;color:var(--success);}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(5px);display:flex;justify-content:center;align-items:center;z-index:1000;display:none;}
.modal-overlay.show{display:flex;}
.modal-box{background:white;border-radius:24px;padding:35px;width:90%;max-width:480px;box-shadow:0 30px 80px rgba(0,0,0,0.3);animation:slideUp 0.3s ease;}
.modal-box h3{font-size:20px;font-weight:800;color:var(--primary);margin-bottom:20px;border-bottom:2px solid var(--light);padding-bottom:12px;}
.modal-actions{display:flex;gap:12px;margin-top:20px;justify-content:flex-end;}
.bill-modal{max-width:560px;}
.bill-header{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;padding:20px 25px;border-radius:16px;text-align:center;margin-bottom:20px;}
.bill-header h2{font-family:'Playfair Display',serif;font-size:24px;}
.bill-header p{font-size:12px;opacity:0.8;margin-top:3px;}
.bill-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #e0e0e0;font-size:14px;}
.bill-item-sub{font-size:11px;color:var(--text-light);margin-top:2px;}
.bill-total-row{display:flex;justify-content:space-between;padding:14px 0 0;font-size:18px;font-weight:900;color:var(--primary);}
.bill-payment-info{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:12px;padding:12px 16px;margin-top:15px;font-size:14px;font-weight:600;}

/* TOAST */
.toast{position:fixed;bottom:30px;right:30px;background:var(--primary);color:white;padding:14px 24px;border-radius:12px;box-shadow:0 8px 25px rgba(0,0,0,0.2);z-index:9999;font-weight:700;transform:translateY(100px);transition:transform 0.3s ease;display:flex;align-items:center;gap:10px;}
.toast.show{transform:translateY(0);}
.toast.toast-error{background:var(--danger);}
.toast.toast-warning{background:var(--warning);}

/* LOW STOCK */
.low-stock-banner{background:linear-gradient(135deg,#fff3e0,#ffe0b2);border:2px solid #ffb74d;border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
.low-stock-banner h4{color:#e65100;font-weight:800;}
.low-stock-banner p{font-size:13px;color:#e65100;opacity:0.85;}

/* LOGOUT */
.logout-btn{background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.3);color:white;padding:9px 20px;border-radius:30px;font-size:13px;font-weight:700;transition:all 0.25s;backdrop-filter:blur(10px);}
.logout-btn:hover{background:rgba(255,255,255,0.25);transform:none;}

/* RESPONSIVE */
@media(max-width:768px){
    .dashboard-grid{grid-template-columns:repeat(2,1fr);}
    .billing-grid{grid-template-columns:1fr;}
    .add-form-grid{grid-template-columns:1fr;}
    .sales-summary{grid-template-columns:repeat(2,1fr);}
    .payment-stats{grid-template-columns:1fr;}
    .page-body{padding:20px 16px;}
    .cat-detail-grid{grid-template-columns:1fr;}
}
@media(max-width:480px){
    .dashboard-grid{grid-template-columns:1fr;}
    .sales-summary{grid-template-columns:1fr;}
    .payment-methods{grid-template-columns:repeat(3,1fr);}
    .login-card{width:95%;padding:30px 24px;}
}
</style>
</head>
<body>

<div id="toast" class="toast"><span id="toastMsg">✅ Done!</span></div>

<!-- LOGIN -->
<div id="loginPage">
    <div class="login-floating-icons">
        <div class="float-icon" style="top:10%;left:10%;animation-delay:0s;">🥦</div>
        <div class="float-icon" style="top:20%;right:15%;animation-delay:1s;">🍎</div>
        <div class="float-icon" style="top:60%;left:5%;animation-delay:2s;">🥛</div>
        <div class="float-icon" style="bottom:15%;right:10%;animation-delay:0.5s;">🛒</div>
        <div class="float-icon" style="bottom:30%;left:20%;animation-delay:1.5s;">🧄</div>
        <div class="float-icon" style="top:40%;right:5%;animation-delay:2.5s;">🍌</div>
        <div class="float-icon" style="top:5%;right:40%;animation-delay:3s;">🥕</div>
        <div class="float-icon" style="bottom:5%;left:40%;animation-delay:0.8s;">🧅</div>
    </div>
    <div class="login-card">
        <div class="logo-wrap">
            <div class="freshmart-logo" style="justify-content:center;flex-direction:column;align-items:center;gap:6px;">
                <div class="logo-icon" style="width:64px;height:64px;font-size:32px;border-radius:18px;">🛒</div>
                <div style="text-align:center;">
                    <div style="font-family:'Playfair Display',serif;font-size:28px;font-weight:800;background:linear-gradient(135deg,#1a6b3a,#27ae60);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">FreshMart</div>
                    <div style="font-size:10px;color:#f39c12;font-weight:700;letter-spacing:3px;text-transform:uppercase;">Grocery Manager Pro</div>
                </div>
            </div>
        </div>
        <h2>Sign in to your account</h2>
        <div class="login-input-group">
            <span class="input-icon">👤</span>
            <input type="text" id="username" placeholder="Username">
        </div>
        <div class="login-input-group">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" placeholder="Password" onkeypress="if(event.key==='Enter')login()">
        </div>
        <button class="login-btn" onclick="login()">🚀 Login to Dashboard</button>
        
    </div>
</div>

<!-- WELCOME -->
<div id="freshmartPage">
    <div class="welcome-content">
        <div class="freshmart-logo freshmart-logo-white" style="justify-content:center;margin-bottom:20px;">
            <div class="logo-icon" style="width:70px;height:70px;font-size:36px;border-radius:20px;">🛒</div>
            <div>
                <div class="logo-text" style="font-size:40px;">FreshMart</div>
                <div class="logo-sub">Grocery Manager Pro 2025</div>
            </div>
        </div>
        <h1>Welcome Back! 👋</h1>
        <p>Your complete grocery management solution</p>
        <button class="enter-btn" onclick="enterDashboard()">Enter Dashboard →</button>
        <div class="welcome-stats">
            <div class="welcome-stat"><div class="stat-num" id="wStatProducts">—</div><div class="stat-label">Products</div></div>
            <div class="welcome-stat"><div class="stat-num" id="wStatSales">—</div><div class="stat-label">Total Sales</div></div>
            <div class="welcome-stat"><div class="stat-num" id="wStatRevenue">—</div><div class="stat-label">Revenue</div></div>
        </div>
    </div>
</div>

<!-- DASHBOARD -->
<div id="dashboard">
    <div class="dashboard-topbar">
        <div class="freshmart-logo freshmart-logo-white">
            <div class="logo-icon">🛒</div>
            <div><div class="logo-text">FreshMart</div><div class="logo-sub">Manager Pro</div></div>
        </div>
        <div style="color:rgba(255,255,255,0.7);font-size:13px;" id="dashUserInfo"></div>
        <button class="logout-btn" onclick="logout()">🚪 Logout</button>
    </div>
    <div class="dashboard-content">
        <div class="dashboard-hero">
            <div class="dashboard-date" id="dashDate"></div>
            <h1>📊 Control Center</h1>
            <p>Manage your grocery store effortlessly</p>
        </div>
        <div class="dashboard-grid">
            <div class="dash-card card-inventory" id="inventoryCard" onclick="showPage('inventory')">
                <span class="card-badge">Live</span><span class="card-icon">📦</span>
                <div class="card-title">Inventory</div><div class="card-desc">Manage Stock</div>
            </div>
            <div class="dash-card card-billing" onclick="showPage('billing')">
                <span class="card-badge">POS</span><span class="card-icon">💰</span>
                <div class="card-title">Billing</div><div class="card-desc">Generate Bills</div>
            </div>
            <div class="dash-card card-addprod" onclick="showPage('addProduct')">
                <span class="card-badge">New</span><span class="card-icon">➕</span>
                <div class="card-title">Add Product</div><div class="card-desc">Expand Catalog</div>
            </div>
            <div class="dash-card card-sales" onclick="showPage('sales')">
                <span class="card-badge">History</span><span class="card-icon">🧾</span>
                <div class="card-title">Sales History</div><div class="card-desc">View Records</div>
            </div>
            <div class="dash-card card-graph" onclick="showPage('salesGraph')">
                <span class="card-badge">Analytics</span><span class="card-icon">📊</span>
                <div class="card-title">Sales Graph</div><div class="card-desc">Visual Reports</div>
            </div>
            <div class="dash-card card-payment" onclick="showPage('paymentHistory')">
                <span class="card-badge">Finance</span><span class="card-icon">💳</span>
                <div class="card-title">Payment History</div><div class="card-desc">Cash · Card · UPI</div>
            </div>
        </div>
    </div>
</div>

<!-- INVENTORY PAGE -->
<div id="inventory" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="backToDashboard()">⬅ Dashboard</button>
        <h2>📦 Inventory</h2>
        <button class="btn-success btn-sm" onclick="loadInventory()">🔄 Refresh</button>
    </div>
    <div class="page-body">
        <div id="lowStockBanner"></div>
        <div class="search-bar"><input type="text" id="searchBox" placeholder="Search all products across categories..."></div>
        <div id="inventoryList"></div>
    </div>
</div>

<!-- CATEGORY DETAIL PAGE -->
<div id="categoryDetail" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="showPage('inventory')">⬅ Back to Inventory</button>
        <h2 id="catDetailTitle">Category</h2>
        <div style="font-size:13px;color:var(--text-light);" id="catDetailCount"></div>
    </div>
    <div class="page-body">
        <div class="search-bar" style="margin-bottom:20px;">
            <input type="text" id="catDetailSearch" placeholder="Search in this category..." oninput="filterCategoryDetail(this.value)">
        </div>
        <div class="cat-detail-grid" id="catDetailGrid"></div>
    </div>
</div>

<!-- ADD PRODUCT PAGE — FIXED: Category first, then filtered brands, GST autocomplete -->
<div id="addProduct" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="goBack()">⬅ Back</button>
        <h2>➕ Add New Product</h2>
    </div>
    <div class="page-body">
        <div class="card">
            <div class="card-header"><h3>🛍️ Product Details</h3></div>
            <div class="add-form-grid">
                <!-- CATEGORY FIRST -->
                <div class="form-group">
                    <label>📂 Category</label>
                    <select id="newCategory" onchange="onCategoryChange()">
                        <option value="">Select Category</option>
                        <option value="dairy">🥛 Dairy</option>
                        <option value="biscuits">🍪 Biscuits</option>
                        <option value="chocolate">🍫 Chocolate</option>
                        <option value="beverages">🥤 Beverages</option>
                        <option value="grains">🌾 Grains</option>
                        <option value="personalCare">🧴 Personal Care</option>
                        <option value="snacks">🍿 Snacks</option>
                        <option value="frozenFoods">🧊 Frozen Foods</option>
                        <option value="bakery">🍞 Bakery</option>
                        <option value="cleaningSupplies">🧹 Cleaning Supplies</option>
                        <option value="healthWellness">💊 Health & Wellness</option>
                        <option value="babyCare">👶 Baby Care</option>
                    </select>
                </div>
                <!-- BRAND FILTERED BY CATEGORY -->
                <div class="form-group">
                    <label>🏷️ Brand</label>
                    <select id="newBrand" disabled>
                        <option value="">Select Category First</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>📝 Product Name</label>
                    <input type="text" id="newName" placeholder="e.g. Amul Butter 500g">
                </div>
                <div class="form-group">
                    <label>💵 Actual Price (₹)</label>
                    <input type="number" id="newPrice" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>📐 Unit</label>
                    <select id="newUnit" onchange="changePackSize()">
                        <option value="">Select Unit</option>
                        <option value="g">Gram (g)</option>
                        <option value="kg">Kilogram (kg)</option>
                        <option value="ml">Millilitre (ml)</option>
                        <option value="L">Litre (L)</option>
                        <option value="pcs">Piece</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>📦 Pack Size</label>
                    <select id="packSize"><option value="">Select Pack Size</option></select>
                </div>
                <div class="form-group">
                    <label>🏪 FreshMart Price (₹)</label>
                    <input type="number" id="newfreshmartPrice" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>📊 GST %</label>
                    <input type="number" id="newGst" placeholder="Auto-filled by category">
                    <div class="gst-hint" id="gstHint"></div>
                </div>
                <div class="form-group full">
                    <label>📋 Stock Quantity</label>
                    <input type="number" id="newQty" placeholder="0">
                </div>
            </div>
            <button class="btn-success" style="width:100%;margin-top:20px;padding:16px;font-size:16px;" onclick="addProduct()">
                ✅ Add Product to Inventory
            </button>
        </div>
    </div>
</div>

<!-- BILLING PAGE -->
<div id="billing" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="goBack()">⬅ Back</button>
        <h2>💰 Billing / POS</h2>
    </div>
    <div class="page-body">
        <div class="billing-grid">
            <div>
                <div class="card">
                    <div class="card-header"><h3>🔍 Find Products</h3></div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:var(--text-light);">🔎 SEARCH PRODUCT</label>
                        <div class="product-search-wrap">
                            <input type="text" id="productSearch" placeholder="Type brand or product name to search..."
                                oninput="onProductSearchInput(this.value)" onkeydown="onProductSearchKey(event)" autocomplete="off">
                            <div class="product-dropdown" id="productDropdown"></div>
                        </div>
                    </div>
                    <div style="margin-top:16px;">
                        <label style="font-size:12px;font-weight:700;color:var(--text-light);">👤 CUSTOMER NAME (optional)</label>
                        <input type="text" id="customerName" placeholder="Customer name...">
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3>🛒 Cart Items</h3>
                        <button class="btn-danger btn-sm" onclick="clearCart()">🗑️ Clear</button>
                    </div>
                    <div id="cartItems"><p style="color:var(--text-light);text-align:center;padding:20px;">Cart is empty. Add products above.</p></div>
                </div>
            </div>
            <div>
                <div class="card" style="position:sticky;top:80px;">
                    <div class="card-header"><h3>💳 Payment</h3></div>
                    <div class="total-box">
                        <div class="total-label">Total Amount</div>
                        <div class="total-amount" id="totalDisplay">₹0.00</div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:700;color:var(--text-light);display:block;margin-bottom:8px;">💳 PAYMENT METHOD</label>
                        <div class="payment-methods">
                            <div class="payment-btn active" id="payCash" onclick="selectPayment('cash')"><span class="pay-icon">💵</span>Cash</div>
                            <div class="payment-btn" id="payCard" onclick="selectPayment('card')"><span class="pay-icon">💳</span>Card</div>
                            <div class="payment-btn" id="payUpi" onclick="selectPayment('upi')"><span class="pay-icon">📱</span>UPI</div>
                        </div>
                    </div>
                    <div id="cashDetails" class="payment-ref-box show" style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-color:#81c784;">
                        <label style="font-size:12px;font-weight:700;color:#1b5e20;">💵 AMOUNT RECEIVED</label>
                        <input type="number" id="cashReceived" placeholder="Enter amount received..." oninput="calcChange()" style="margin-top:8px;">
                        <div id="changeDisplay" style="font-size:15px;font-weight:800;color:#1b5e20;margin-top:8px;display:none;">
                            💰 Change to return: <span id="changeAmt">₹0</span>
                        </div>
                    </div>
                    <div id="cardDetails" class="payment-ref-box">
                        <label style="font-size:12px;font-weight:700;color:#0d47a1;">💳 CARD TYPE</label>
                        <div class="card-type-btns">
                            <button class="upi-app-btn" onclick="selectCardType(this,'Visa')">💳 Visa</button>
                            <button class="upi-app-btn" onclick="selectCardType(this,'Mastercard')">💳 Mastercard</button>
                            <button class="upi-app-btn" onclick="selectCardType(this,'Rupay')">💳 RuPay</button>
                            <button class="upi-app-btn" onclick="selectCardType(this,'Amex')">💳 Amex</button>
                        </div>
                        <label style="font-size:12px;font-weight:700;color:#0d47a1;display:block;margin-top:10px;">LAST 4 DIGITS</label>
                        <input type="number" id="cardLast4" placeholder="e.g. 1234" maxlength="4" style="margin-top:5px;">
                    </div>
                    <div id="upiDetails" class="payment-ref-box">
                        <label style="font-size:12px;font-weight:700;color:#4a148c;">📱 UPI APP</label>
                        <div class="upi-apps">
                            <button class="upi-app-btn" onclick="selectUpiApp(this,'GPay')">🟢 GPay</button>
                            <button class="upi-app-btn" onclick="selectUpiApp(this,'PhonePe')">🟣 PhonePe</button>
                            <button class="upi-app-btn" onclick="selectUpiApp(this,'Paytm')">🔵 Paytm</button>
                            <button class="upi-app-btn" onclick="selectUpiApp(this,'BHIM')">🟠 BHIM</button>
                            <button class="upi-app-btn" onclick="selectUpiApp(this,'Amazon Pay')">🟡 Amazon Pay</button>
                        </div>
                        <label style="font-size:12px;font-weight:700;color:#4a148c;display:block;margin-top:10px;">UPI TRANSACTION ID</label>
                        <input type="text" id="upiTxnId" placeholder="e.g. GPay ref no..." style="margin-top:5px;">
                        <label style="font-size:12px;font-weight:700;color:#4a148c;display:block;margin-top:10px;">UPI NUMBER / VPA</label>
                        <input type="text" id="upiNumber" placeholder="e.g. 98765@gpay" style="margin-top:5px;">
                    </div>
                    <button class="btn-success" style="width:100%;margin-top:16px;padding:16px;font-size:15px;" onclick="generateBill()">
                        🧾 Generate Bill
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SALES HISTORY -->
<div id="sales" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="goBack()">⬅ Back</button>
        <h2>🧾 Sales History</h2>
        <button class="btn-success btn-sm" onclick="exportCSV()">📥 Export CSV</button>
    </div>
    <div class="page-body">
        <div class="sales-summary">
            <div class="summary-card"><div class="sum-icon">🧾</div><div class="sum-value" id="sumTotalBills">0</div><div class="sum-label">Total Bills</div></div>
            <div class="summary-card"><div class="sum-icon">💰</div><div class="sum-value" id="sumRevenue">₹0</div><div class="sum-label">Revenue</div></div>
            <div class="summary-card"><div class="sum-icon">📦</div><div class="sum-value" id="sumItemsSold">0</div><div class="sum-label">Items Sold</div></div>
            <div class="summary-card"><div class="sum-icon">📅</div><div class="sum-value" id="sumToday">₹0</div><div class="sum-label">Today's Sales</div></div>
        </div>
        <div class="sales-filters">
            <div class="filter-group"><label>📅 From Date</label><input type="date" id="filterFrom"></div>
            <div class="filter-group"><label>📅 To Date</label><input type="date" id="filterTo"></div>
            <div class="filter-group"><label>💳 Payment</label>
                <select id="filterPayment">
                    <option value="">All Methods</option>
                    <option value="cash">💵 Cash</option>
                    <option value="card">💳 Card</option>
                    <option value="upi">📱 UPI</option>
                </select>
            </div>
            <div class="filter-group"><label>🔍 Search</label><input type="text" id="salesSearch" placeholder="Product / Customer..."></div>
            <button class="btn-primary btn-sm" onclick="applySalesFilter()" style="align-self:flex-end;">Filter</button>
            <button class="btn-warning btn-sm" onclick="resetSalesFilter()" style="align-self:flex-end;">Reset</button>
        </div>
        <div id="salesList"></div>
    </div>
</div>

<!-- PAYMENT HISTORY -->
<div id="paymentHistory" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="goBack()">⬅ Back</button>
        <h2>💳 Payment History</h2>
        <button class="btn-success btn-sm" onclick="exportPaymentCSV()">📥 Export</button>
    </div>
    <div class="page-body">
        <div class="payment-stats">
            <div class="pay-stat-card pay-stat-cash"><div class="stat-icon">💵</div><div class="stat-amount" id="cashTotal">₹0</div><div class="stat-count" id="cashCount">0 transactions</div><div class="stat-label">Cash</div></div>
            <div class="pay-stat-card pay-stat-card2"><div class="stat-icon">💳</div><div class="stat-amount" id="cardTotal">₹0</div><div class="stat-count" id="cardCount">0 transactions</div><div class="stat-label">Card</div></div>
            <div class="pay-stat-card pay-stat-upi"><div class="stat-icon">📱</div><div class="stat-amount" id="upiTotal">₹0</div><div class="stat-count" id="upiCount">0 transactions</div><div class="stat-label">UPI</div></div>
        </div>
        <div class="sales-filters" style="border-color:#e1bee7;">
            <div class="filter-group"><label>💳 Payment Method</label>
                <select id="payFilterMethod">
                    <option value="">All Methods</option>
                    <option value="cash">💵 Cash</option>
                    <option value="card">💳 Card</option>
                    <option value="upi">📱 UPI</option>
                </select>
            </div>
            <div class="filter-group"><label>📅 From Date</label><input type="date" id="payFilterFrom"></div>
            <div class="filter-group"><label>📅 To Date</label><input type="date" id="payFilterTo"></div>
            <button class="btn-purple btn-sm" onclick="applyPaymentFilter()" style="align-self:flex-end;">Filter</button>
            <button class="btn-warning btn-sm" onclick="resetPayFilter()" style="align-self:flex-end;">Reset</button>
        </div>
        <div class="payment-table">
            <table>
                <thead><tr><th>#</th><th>Bill ID</th><th>Date & Time</th><th>Customer</th><th>Items</th><th>Payment</th><th>Reference</th><th>Amount</th></tr></thead>
                <tbody id="paymentTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- SALES GRAPH -->
<div id="salesGraph" class="page">
    <div class="page-topbar">
        <div class="freshmart-logo"><div class="logo-icon" style="width:36px;height:36px;font-size:18px;border-radius:10px;">🛒</div></div>
        <button class="btn-primary btn-sm" onclick="goBack()">⬅ Back</button>
        <h2>📊 Sales Analytics</h2>
    </div>
    <div class="page-body">
        <div class="chart-container">
            <h3>📈 Sales Timeline</h3>
            <div class="graph-tabs">
                <div class="graph-tab active" onclick="switchGraphTab('day',this)">📅 Day</div>
                <div class="graph-tab" onclick="switchGraphTab('month',this)">📆 Month</div>
                <div class="graph-tab" onclick="switchGraphTab('year',this)">🗓️ Year</div>
            </div>
            <canvas id="salesTimelineChart" height="100"></canvas>
        </div>
        <div class="chart-container">
            <h3>🥧 Top Selling Products</h3>
            <canvas id="topProductsChart" height="100"></canvas>
        </div>
        <div class="chart-container">
            <h3>💳 Revenue by Payment Method</h3>
            <canvas id="paymentMethodChart" height="80"></canvas>
        </div>
        <div class="card">
            <div class="card-header"><h3>🏆 Best Selling Products</h3></div>
            <div id="topProductsList"></div>
        </div>
    </div>
</div>

<!-- BILL MODAL -->
<div class="modal-overlay" id="billModal">
    <div class="modal-box bill-modal">
        <div class="bill-header"><div style="font-size:28px;">🛒</div><h2>FreshMart</h2><p>Official Tax Invoice</p></div>
        <div id="billContent"></div>
        <div class="modal-actions">
            <button class="btn-primary" onclick="printBill()">🖨️ Print</button>
            <button class="btn-success" onclick="closeBillModal()">✅ Done</button>
        </div>
    </div>
</div>

<script>
// ============================================================
// DATA
// ============================================================
let role = "";
let inventory = [];
let cart = [];
let sales = [];
let salesData = [];
let payments = [];
let currentCategoryItems = [];
let salesTimelineChart = null;
let topProductsChart = null;
let paymentMethodChart = null;
let currentGraphMode = 'day';
let selectedPaymentMethod = 'cash';
let selectedCardType = '';
let selectedUpiApp = '';
let dropdownFocusIndex = -1;

const categoryIcons = {
    dairy:'🥛', biscuits:'🍪', chocolate:'🍫', beverages:'🥤',
    grains:'🌾', personalCare:'🧴', snacks:'🍿', frozenFoods:'🧊',
    bakery:'🍞', cleaningSupplies:'🧹', healthWellness:'💊', babyCare:'👶',
    'Uncategorized':'📦'
};

// ============================================================
// FIX 1: CATEGORY → BRAND MAPPING (filtered brands per category)
// ============================================================
const categoryBrands = {
    dairy:         ['Amul','Aavin','Heritage','Nandini','Mother Dairy','Milma','Nestle','Britannia','Go Cheese','Saras'],
    biscuits:      ['Britannia','Parle','Sunfeast','McVities','Oreo','Hide & Seek','Horlicks','Priyagold','Anmol','Unibic'],
    chocolate:     ['Cadbury','Nestlé','KitKat','Ferrero','Lindt','Hershey','Milky Bar','Munch','5 Star','Bounty'],
    beverages:     ['Coca Cola','Pepsi','Sprite','Tropicana','Real','Minute Maid','7Up','Limca','Maaza','Frooti','Paper Boat','Red Bull','Monster','Bisleri','Kinley'],
    grains:        ['Fortune','Daawat','India Gate','Kohinoor','Patanjali','Aashirvaad','Nature Fresh','Rajdhani','24 Mantra','Saffola'],
    personalCare:  ['Dove','Lux','Dettol','Lifebuoy','Colgate','Pepsodent','Clinic Plus','Head & Shoulders','Pantene','Sunsilk','Gillette','Veet','Nivea','Ponds','Fair & Lovely','Garnier'],
    snacks:        ['Lays','Haldiram\'s','Bingo','Kurkure','Parle','Too Yumm','Pringles','Doritos','Act II','Balaji'],
    frozenFoods:   ['McCain','Al Kabeer','Mother\'s Recipe','ITC','Godrej','Venky\'s','Suguna','Cremica'],
    bakery:        ['Harvest Gold','Modern','Britannia','English Oven','Wibs','Nature\'s Own','Bonn','Monginis'],
    cleaningSupplies:['Vim','Harpic','Domex','Colin','Lizol','Scotch-Brite','Surf Excel','Ariel','Tide','Rin','Nirma','Ujala'],
    healthWellness:['Horlicks','Complan','Boost','Ensure','Protinex','Bournvita','Amway','Himalaya','Dabur','Patanjali','Zandu'],
    babyCare:      ['Pampers','Huggies','Johnson\'s','Himalaya Baby','Mamy Poko','Chicco','Sebamed','Mamaearth','Mother Sparsh']
};

// ============================================================
// FIX 2: GST AUTOCOMPLETE BY CATEGORY
// ============================================================
const categoryGST = {
    dairy: 5,
    biscuits: 18,
    chocolate: 28,
    beverages: 18,
    grains: 5,
    personalCare: 18,
    snacks: 12,
    frozenFoods: 12,
    bakery: 5,
    cleaningSupplies: 18,
    healthWellness: 12,
    babyCare: 12
};

// Called when category changes — loads brands + autofills GST
function onCategoryChange(){
    const cat = document.getElementById("newCategory").value;
    const brandSelect = document.getElementById("newBrand");
    const gstInput = document.getElementById("newGst");
    const gstHint = document.getElementById("gstHint");

    // Reset brand
    brandSelect.innerHTML = '<option value="">Select Brand</option>';

    if(!cat){
        brandSelect.disabled = true;
        brandSelect.innerHTML = '<option value="">Select Category First</option>';
        gstInput.value = '';
        gstHint.classList.remove('show');
        return;
    }

    // Load filtered brands
    const brands = categoryBrands[cat] || [];
    brands.forEach(b => {
        brandSelect.innerHTML += `<option value="${b}">${b}</option>`;
    });
    brandSelect.disabled = false;

    // Autocomplete GST
    const gst = categoryGST[cat];
    if(gst !== undefined){
        gstInput.value = gst;
        gstHint.textContent = `✅ GST auto-filled: ${gst}% (standard rate for this category)`;
        gstHint.classList.add('show');
    } else {
        gstInput.value = '';
        gstHint.classList.remove('show');
    }
}

// ============================================================
// INIT
// ============================================================
window.addEventListener("DOMContentLoaded", function(){
    updateDashDate();
    loadInventoryData();
    loadSalesData();

    const searchBox = document.getElementById("searchBox");
    if(searchBox){
        searchBox.addEventListener("input", function(){
            const q = this.value.toLowerCase();
            const filtered = inventory.filter(item =>
                item.name.toLowerCase().includes(q) ||
                (item.brand||'').toLowerCase().includes(q)
            );
            renderInventory(filtered);
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e){
        if(!e.target.closest('.product-search-wrap')){
            closeProductDropdown();
        }
    });
});

function updateDashDate(){
    const now = new Date();
    const opts = {weekday:'long',year:'numeric',month:'long',day:'numeric'};
    document.getElementById("dashDate").textContent = now.toLocaleDateString('en-IN', opts);
}

function showToast(msg, type="success"){
    const t = document.getElementById("toast");
    const m = document.getElementById("toastMsg");
    t.className = "toast" + (type==="error"?" toast-error":type==="warning"?" toast-warning":"");
    m.textContent = msg;
    t.classList.add("show");
    setTimeout(()=>t.classList.remove("show"), 3200);
}

// ============================================================
// DATA LOADING
// ============================================================
function loadInventoryData(){
    fetch("index.php?action=get_inventory")
    .then(res=>res.json())
    .then(data=>{
        inventory = data.map(item=>({
            id: item.id,
            name: item.product_name,
            actual: parseFloat(item.price),
            gst: parseFloat(item.gst ?? 0),
            fresh: parseFloat(item.fresh_price ?? 0),
            qty: parseInt(item.quantity),
            category: item.category || 'Uncategorized',
            pack: item.pack_size || "",
            unit: item.unit || "",
            brand: item.brand || ""
        }));
        renderInventory();
        updateWelcomeStats();
    })
    .catch(err=>console.error(err));
}

function loadSalesData(){
    fetch("index.php?action=get_sales")
    .then(res=>res.json())
    .then(data=>{
        salesData = data.map(item=>({
            product_name: item.product_name,
            qty: parseInt(item.qty),
            total: parseFloat(item.total),
            fresh_price: parseFloat(item.fresh_price),
            actual_price: parseFloat(item.actual_price),
            gst: parseFloat(item.gst),
            date: item.date,
            time: item.time,
            bill_id: item.bill_id,
            payment_method: item.payment_method || 'cash',
            payment_ref: item.payment_ref || '',
            customer_name: item.customer_name || '',
            brand: item.brand || '',
            pack_size: item.pack_size || '',
            unit: item.unit || ''
        }));
        sales = groupSalesByBill(data);
        renderSales();
        updateWelcomeStats();
    })
    .catch(err=>console.error(err));
}

function groupSalesByBill(data){
    const groups = {};
    data.forEach(item=>{
        const key = item.bill_id || (item.date + "_" + item.time);
        if(!groups[key]){
            groups[key] = {
                billId: item.bill_id || key,
                time: item.date + " " + item.time,
                items: [],
                total: parseFloat(item.total),
                paymentMethod: item.payment_method || 'cash',
                paymentRef: item.payment_ref || '',
                customerName: item.customer_name || ''
            };
        }
        groups[key].items.push({
            name: item.product_name,
            actual: parseFloat(item.actual_price),
            gst: parseFloat(item.gst),
            fresh: parseFloat(item.fresh_price),
            qty: parseInt(item.qty),
            unit: item.unit || '',
            brand: item.brand || '',
            pack: item.pack_size || ''
        });
    });
    return Object.values(groups).reverse();
}

function updateWelcomeStats(){
    document.getElementById("wStatProducts").textContent = inventory.length;
    document.getElementById("wStatSales").textContent = sales.length;
    const rev = sales.reduce((s,b)=>s+b.total,0);
    document.getElementById("wStatRevenue").textContent = "₹"+rev.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,",");
}

function loadInventory(){ loadInventoryData(); }

// ============================================================
// RENDER INVENTORY
// ============================================================
function renderInventory(filteredInventory){
    const inventoryList = document.getElementById("inventoryList");
    if(!inventoryList) return;
    inventoryList.innerHTML = "";
    const items = filteredInventory || inventory;

    const grouped = {};
    items.forEach(item=>{
        let cat = item.category || "Uncategorized";
        if(!grouped[cat]) grouped[cat]=[];
        grouped[cat].push(item);
    });

    if(Object.keys(grouped).length===0){
        inventoryList.innerHTML=`<div class="card" style="text-align:center;padding:40px;color:var(--text-light);">📭 No products found.</div>`;
        return;
    }

    for(const cat in grouped){
        const icon = categoryIcons[cat] || "📦";
        const count = grouped[cat].length;
        const lowCount = grouped[cat].filter(p=>p.qty>0&&p.qty<5).length;
        const outCount = grouped[cat].filter(p=>p.qty===0).length;

        inventoryList.innerHTML += `
        <div class="category-card" onclick="openCategoryDetail('${cat}')">
            <div class="category-header">
                <span class="category-icon">${icon}</span>
                <span class="category-name">${capitalize(cat)}</span>
                ${lowCount>0?`<span style="background:#fff3e0;color:#e65100;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">⚠️ ${lowCount} low</span>`:''}
                ${outCount>0?`<span style="background:#ffebee;color:#b71c1c;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">🔴 ${outCount} out</span>`:''}
                <span class="category-count">${count} items</span>
                <span style="font-size:16px;color:var(--text-light);">▶</span>
            </div>
        </div>`;
    }
    checkLowStock();
}

function openCategoryDetail(cat){
    currentCategoryItems = inventory.filter(p => p.category === cat);
    const icon = categoryIcons[cat] || "📦";
    document.getElementById("catDetailTitle").textContent = icon + " " + capitalize(cat);
    document.getElementById("catDetailCount").textContent = currentCategoryItems.length + " products";
    document.getElementById("catDetailSearch").value = "";
    renderCategoryDetailGrid(currentCategoryItems);
    document.querySelectorAll(".page").forEach(p=>p.classList.remove("active"));
    document.getElementById("dashboard").style.display="none";
    document.getElementById("categoryDetail").classList.add("active");
}

function filterCategoryDetail(query){
    query = query.toLowerCase();
    const filtered = currentCategoryItems.filter(p=>
        p.name.toLowerCase().includes(query) ||
        (p.brand||'').toLowerCase().includes(query) ||
        (p.pack||'').toString().includes(query)
    );
    renderCategoryDetailGrid(filtered);
}

function renderCategoryDetailGrid(items){
    const grid = document.getElementById("catDetailGrid");
    if(!items||items.length===0){
        grid.innerHTML=`<div class="card" style="text-align:center;padding:40px;color:var(--text-light);">📭 No products found.</div>`;
        return;
    }
    const freshMartColor='#1a6b3a';
    grid.innerHTML = items.map(p=>{
        const freshPrice = p.fresh > 0 ? p.fresh : (p.actual + (p.actual * p.gst / 100));
        const maxQty = 50;
        const stockPct = Math.min(100, (p.qty / maxQty) * 100);
        const stockLabel = p.qty===0?'<span class="stock-badge stock-out">🔴 Out of Stock</span>':
                           p.qty<5?'<span class="stock-badge stock-low">🟡 Low Stock</span>':
                           '<span class="stock-badge stock-ok">✅ In Stock</span>';
        return `
        <div class="cat-product-card">
            <div class="cat-product-name">${p.name}${p.pack?` <span style="font-size:12px;color:var(--text-light);">(${p.pack}${p.unit})</span>`:''}</div>
            <div class="cat-product-brand">🏷️ ${p.brand||'Unknown Brand'}</div>
            <div class="cat-product-details">
                <div class="cat-detail-chip"><span class="chip-label">Original Price</span><span class="chip-value">₹${p.actual.toFixed(2)}</span></div>
                <div class="cat-detail-chip" style="background:#e8f5e9;"><span class="chip-label">FreshMart Price</span><span class="chip-value" style="color:${freshMartColor};font-weight:900;">₹${freshPrice.toFixed(2)}</span></div>
                <div class="cat-detail-chip"><span class="chip-label">GST</span><span class="chip-value">${p.gst}%</span></div>
                <div class="cat-detail-chip"><span class="chip-label">Pack Size</span><span class="chip-value">${p.pack?p.pack+p.unit:'—'}</span></div>
                <div class="cat-detail-chip" style="grid-column:1/-1;"><span class="chip-label">Stock</span><span class="chip-value">${p.qty} units &nbsp; ${stockLabel}</span></div>
            </div>
            <div class="cat-product-stock-bar">
                <div class="cat-product-stock-fill" style="width:${stockPct}%;background:${p.qty===0?'#e74c3c':p.qty<5?'#f39c12':'#27ae60'};"></div>
            </div>
            <div class="cat-product-actions">
                <button class="btn-primary btn-sm" style="flex:1;" onclick="event.stopPropagation();editProductFromDetail(${p.id})">✏️ Edit</button>
                <button class="btn-danger btn-sm" onclick="event.stopPropagation();deleteProductFromDetail(${p.id})">🗑️</button>
            </div>
        </div>`;
    }).join("");
}

function editProductFromDetail(id){
    let p = inventory.find(x=>x.id==id);
    if(!p) return;
    let name = prompt("Product Name:", p.name);
    let price = prompt("Price (₹):", p.actual);
    let gst = prompt("GST %:", p.gst);
    let qty = prompt("Add to Stock (enter 0 for no change):", 0);
    if(name!==null) p.name=name;
    if(price!==null) p.actual=parseFloat(price);
    if(gst!==null) p.gst=parseFloat(gst);
    let addQty=parseInt(qty)||0;
    if(addQty) p.qty+=addQty;
    fetch("index.php",{method:"POST",headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:"update_inventory",id:p.id,name:p.name,price:p.actual,gst:p.gst,addStock:addQty})
    }).then(()=>{
        showToast("✅ Product updated!");
        renderCategoryDetailGrid(currentCategoryItems);
    });
}

function deleteProductFromDetail(id){
    if(!confirm("Delete this product from inventory?")) return;
    fetch("index.php",{method:"POST",body:JSON.stringify({action:"delete_inventory",id:id})})
    .then(res=>res.json())
    .then(data=>{
        if(data.status==="success"){
            inventory=inventory.filter(x=>x.id!=id);
            currentCategoryItems=currentCategoryItems.filter(x=>x.id!=id);
            showToast("🗑️ Product deleted!");
            renderCategoryDetailGrid(currentCategoryItems);
            document.getElementById("catDetailCount").textContent=currentCategoryItems.length+" products";
        } else {
            showToast("Error deleting product","error");
        }
    });
}

function checkLowStock(){
    const low=inventory.filter(p=>p.qty>0&&p.qty<5);
    const out=inventory.filter(p=>p.qty===0);
    const banner=document.getElementById("lowStockBanner");
    if(!banner) return;
    if(low.length>0||out.length>0){
        banner.innerHTML=`<div class="low-stock-banner"><span style="font-size:28px;">⚠️</span><div><h4>Stock Alert!</h4><p>${out.length>0?`<b>${out.length} out of stock</b> · `:''} ${low.length} products running low: ${low.slice(0,5).map(p=>p.name+"("+p.qty+")").join(", ")}${low.length>5?' ...':''}</p></div></div>`;
    } else {
        banner.innerHTML='';
    }
}

function capitalize(str){ return str?str.charAt(0).toUpperCase()+str.slice(1):''; }

// ============================================================
// ADD PRODUCT
// ============================================================
function changePackSize(){
    let unit=document.getElementById("newUnit").value;
    let packSelect=document.getElementById("packSize");
    packSelect.innerHTML="<option value=''>Select Pack Size</option>";
    let sizes=[];
    switch(unit){
        case "g": sizes=[1,5,10,20,30,50,60,75,100,200]; break;
        case "kg": sizes=[0.25,0.5,1,2,5]; break;
        case "ml": sizes=[1,5,10,50,60,75,100,500]; break;
        case "L": sizes=[0.25,0.5,1,2,5]; break;
        case "pcs": sizes=[1,2,3,5,10,20,30]; break;
    }
    sizes.forEach(s=>{ packSelect.innerHTML+=`<option value="${s}">${s}${unit}</option>`; });
}

function addProduct(){
    let name=document.getElementById("newName").value.trim();
    let price=parseFloat(document.getElementById("newPrice").value);
    let gst=parseFloat(document.getElementById("newGst").value)||0;
    let qty=parseInt(document.getElementById("newQty").value);
    let unit=document.getElementById("newUnit").value;
    let pack=document.getElementById("packSize").value;
    let brand=document.getElementById("newBrand").value;
    let category=document.getElementById("newCategory").value.trim();
    let freshPrice=parseFloat(document.getElementById("newfreshmartPrice").value)||0;

    if(!name||isNaN(price)||isNaN(qty)||!unit||!pack||!brand||!category){
        showToast("⚠️ Please fill all fields","warning"); return;
    }

    const newItem={id:Date.now(),name,actual:price,gst,fresh:freshPrice,qty,unit,pack,brand,category};
    inventory.push(newItem);
    showToast("✅ Product added successfully!");
    updateWelcomeStats();

    // Reset form
    document.getElementById("newName").value="";
    document.getElementById("newPrice").value="";
    document.getElementById("newGst").value="";
    document.getElementById("newQty").value="";
    document.getElementById("newfreshmartPrice").value="";
    document.getElementById("newUnit").value="";
    document.getElementById("packSize").innerHTML="<option value=''>Select Pack Size</option>";
    document.getElementById("newBrand").value="";
    document.getElementById("newBrand").disabled=true;
    document.getElementById("newBrand").innerHTML="<option value=''>Select Category First</option>";
    document.getElementById("newCategory").value="";
    document.getElementById("gstHint").classList.remove('show');

    fetch("index.php",{method:"POST",headers:{'Content-Type':'application/json'},
        body:JSON.stringify({name,actual:price,gst,qty,unit,packSize:pack,brand,category,fresh:freshPrice})
    }).then(()=>{ loadInventory(); });
}

// ============================================================
// BILLING — LIVE SEARCH DROPDOWN
// ============================================================
function onProductSearchInput(value){
    dropdownFocusIndex=-1;
    if(!value||value.trim().length===0){ closeProductDropdown(); return; }
    const q=value.toLowerCase().trim();
    const results=inventory.filter(p=>
        p.qty>0&&(
            p.name.toLowerCase().includes(q)||
            (p.brand||'').toLowerCase().includes(q)||
            (p.pack||'').toString().includes(q)||
            (p.category||'').toLowerCase().includes(q)
        )
    ).slice(0,12);
    renderProductDropdown(results);
}

function renderProductDropdown(results){
    const dd=document.getElementById("productDropdown");
    if(results.length===0){
        dd.innerHTML=`<div class="product-dropdown-empty">🔍 No products found. Try a different keyword.</div>`;
        dd.classList.add("open"); return;
    }
    dd.innerHTML=results.map(p=>{
        const freshPrice=p.fresh>0?p.fresh:(p.actual+(p.actual*p.gst/100));
        const stockColor=p.qty<5?'#e65100':'#1b5e20';
        return `
        <div class="product-dropdown-item" data-id="${p.id}" onclick="selectProductFromDropdown(${p.id})">
            <span class="pd-name">${p.name}${p.pack?` <small style="font-weight:600;color:#888;">(${p.pack}${p.unit})</small>`:''}</span>
            <span class="pd-meta">${p.brand?'🏷️ '+p.brand:''} · 📦 ${capitalize(p.category||'')}</span>
            <span class="pd-price">₹${freshPrice.toFixed(2)} &nbsp; <span style="font-size:11px;color:${stockColor};font-weight:700;">Stock: ${p.qty}</span></span>
        </div>`;
    }).join("");
    dd.classList.add("open");
}

function closeProductDropdown(){
    document.getElementById("productDropdown").classList.remove("open");
    dropdownFocusIndex=-1;
}

function onProductSearchKey(e){
    const dd=document.getElementById("productDropdown");
    const items=dd.querySelectorAll(".product-dropdown-item");
    if(!dd.classList.contains("open")||items.length===0) return;
    if(e.key==="ArrowDown"){e.preventDefault();dropdownFocusIndex=Math.min(dropdownFocusIndex+1,items.length-1);updateDropdownFocus(items);}
    else if(e.key==="ArrowUp"){e.preventDefault();dropdownFocusIndex=Math.max(dropdownFocusIndex-1,0);updateDropdownFocus(items);}
    else if(e.key==="Enter"){e.preventDefault();if(dropdownFocusIndex>=0&&items[dropdownFocusIndex]){const id=parseInt(items[dropdownFocusIndex].dataset.id);selectProductFromDropdown(id);}}
    else if(e.key==="Escape"){closeProductDropdown();}
}

function updateDropdownFocus(items){
    items.forEach(it=>it.classList.remove("focused"));
    if(dropdownFocusIndex>=0) items[dropdownFocusIndex].classList.add("focused");
}

function selectProductFromDropdown(productId){
    const p=inventory.find(x=>x.id==productId);
    if(!p||p.qty<=0){showToast("Product not available","warning");return;}
    let qty=parseInt(prompt(`How many units of "${p.name}"?`,1));
    if(isNaN(qty)||qty<1) return;
    if(qty>p.qty){showToast("Not enough stock!","error");return;}
    const fresh=p.fresh>0?p.fresh:(p.actual+(p.actual*p.gst/100));
    const existing=cart.find(c=>c.id===p.id);
    if(existing){existing.qty+=qty;}
    else{cart.push({id:p.id,name:p.name,brand:p.brand||'',pack:p.pack||'',unit:p.unit||'',actual:p.actual,gst:p.gst,fresh,qty});}
    p.qty-=qty;
    renderCart();
    renderInventory();
    document.getElementById("productSearch").value="";
    closeProductDropdown();
}

// ============================================================
// BILLING PAYMENT
// ============================================================
function selectPayment(method){
    selectedPaymentMethod=method;
    ['payCash','payCard','payUpi'].forEach(id=>document.getElementById(id).classList.remove('active'));
    if(method==='cash') document.getElementById('payCash').classList.add('active');
    if(method==='card') document.getElementById('payCard').classList.add('active');
    if(method==='upi') document.getElementById('payUpi').classList.add('active');
    document.getElementById('cashDetails').classList.toggle('show',method==='cash');
    document.getElementById('cardDetails').classList.toggle('show',method==='card');
    document.getElementById('upiDetails').classList.toggle('show',method==='upi');
}

function selectCardType(el,type){
    selectedCardType=type;
    document.querySelectorAll('.card-type-btns .upi-app-btn').forEach(b=>b.classList.remove('selected'));
    el.classList.add('selected');
}

function selectUpiApp(el,app){
    selectedUpiApp=app;
    document.querySelectorAll('.upi-apps .upi-app-btn').forEach(b=>b.classList.remove('selected'));
    el.classList.add('selected');
}

function calcChange(){
    const total=cart.reduce((s,c)=>s+(c.fresh*c.qty),0);
    const received=parseFloat(document.getElementById("cashReceived").value)||0;
    const change=received-total;
    const disp=document.getElementById("changeDisplay");
    const amt=document.getElementById("changeAmt");
    if(received>0){
        disp.style.display="block";
        amt.textContent="₹"+(change>=0?change.toFixed(2):"0.00 (Short by ₹"+Math.abs(change).toFixed(2)+")");
        amt.style.color=change>=0?"#1b5e20":"#c0392b";
    } else {
        disp.style.display="none";
    }
}

function clearCart(){
    cart.forEach(c=>{let p=inventory.find(x=>x.id===c.id);if(p) p.qty+=c.qty;});
    cart=[];renderCart();renderInventory();
}

function renderCart(){
    const cartItems=document.getElementById("cartItems");
    let total=0;
    if(cart.length===0){
        cartItems.innerHTML='<p style="color:var(--text-light);text-align:center;padding:20px;">Cart is empty.</p>';
        document.getElementById("totalDisplay").textContent="₹0.00";
        return;
    }
    cartItems.innerHTML=cart.map((c,i)=>{
        total+=c.fresh*c.qty;
        return `<div class="cart-item">
            <div>
                <div class="cart-item-name">${c.name}</div>
                <div style="font-size:12px;color:var(--text-light);">${c.brand?c.brand+' · ':''}${c.pack?c.pack+c.unit+' · ':''}₹${c.fresh.toFixed(2)} × ${c.qty}</div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="cart-item-price">₹${(c.fresh*c.qty).toFixed(2)}</div>
                <input type="number" min="1" value="${c.qty}" style="width:55px;padding:6px;margin:0;" onchange="updateQty(${i},this.value)">
                <button class="btn-danger btn-sm" onclick="removeItem(${i})">✕</button>
            </div>
        </div>`;
    }).join("");
    document.getElementById("totalDisplay").textContent="₹"+total.toFixed(2);
    calcChange();
}

function updateQty(i,value){
    value=parseInt(value);
    if(isNaN(value)||value<1) value=1;
    let product=inventory.find(x=>x.id===cart[i].id);
    let maxQty=cart[i].qty+(product?product.qty:0);
    if(value>maxQty){showToast("Not enough stock","warning");value=maxQty;}
    if(product) product.qty+=cart[i].qty-value;
    cart[i].qty=value;
    renderCart();renderInventory();
}

function removeItem(i){
    let p=inventory.find(x=>x.id===cart[i].id);
    if(p) p.qty+=cart[i].qty;
    cart.splice(i,1);
    renderCart();renderInventory();
}

// ============================================================
// GENERATE BILL
// ============================================================
function generateBill(){
    if(cart.length===0){showToast("Cart is empty","warning");return;}
    let payRef='';
    if(selectedPaymentMethod==='cash'){
        const recv=parseFloat(document.getElementById("cashReceived").value)||0;
        payRef=recv>0?`Cash received: ₹${recv.toFixed(2)}`:'';
    } else if(selectedPaymentMethod==='card'){
        if(!document.getElementById("cardLast4").value){showToast("Enter last 4 digits","warning");return;}
        payRef=`${selectedCardType||'Card'} ending ${document.getElementById("cardLast4").value}`;
    } else if(selectedPaymentMethod==='upi'){
        const txn=document.getElementById("upiTxnId").value;
        const num=document.getElementById("upiNumber").value;
        payRef=`${selectedUpiApp||'UPI'}${txn?' | Txn:'+txn:''}${num?' | '+num:''}`;
    }

    const customerName=document.getElementById("customerName").value;
    let total=cart.reduce((s,c)=>s+(c.fresh*c.qty),0);
    let time=new Date().toLocaleString('en-IN');
    let billItems=JSON.parse(JSON.stringify(cart));
    let billId="BILL"+Date.now();

    showBillModal({items:billItems,total,time,paymentMethod:selectedPaymentMethod,paymentRef:payRef,customerName,
        cashReceived:selectedPaymentMethod==='cash'?parseFloat(document.getElementById("cashReceived").value)||0:0});

    const todayDate=new Date().toISOString().slice(0,10);
    const newBillEntry={billId,time,items:billItems,total,paymentMethod:selectedPaymentMethod,paymentRef:payRef,customerName};
    sales.unshift(newBillEntry);

    // Add to salesData for graphs — FIX: ensure salesData gets updated properly
    billItems.forEach(item=>{
        salesData.push({
            product_name:item.name,
            qty:item.qty,
            total,
            fresh_price:item.fresh,
            actual_price:item.actual,
            gst:item.gst,
            date:todayDate,
            time:new Date().toLocaleTimeString(),
            bill_id:billId,
            payment_method:selectedPaymentMethod,
            payment_ref:payRef,
            customer_name:customerName,
            brand:item.brand||'',
            pack_size:item.pack||'',
            unit:item.unit||''
        });
    });

    payments.unshift({
        id:Date.now(),billId,time,date:todayDate,customerName,
        paymentMethod:selectedPaymentMethod,paymentRef:payRef,amount:total,
        itemsList:billItems.map(i=>i.name+'|'+i.qty+'|'+(i.brand||'')+'|'+(i.pack||'')).join(';;')
    });

    fetch("index.php",{method:"POST",headers:{"Content-Type":"application/json"},
        body:JSON.stringify({billId,items:billItems,total,paymentMethod:selectedPaymentMethod,paymentRef:payRef,customerName})
    });

    updateWelcomeStats();
    cart=[];
    document.getElementById("cashReceived").value="";
    document.getElementById("upiTxnId").value="";
    document.getElementById("upiNumber").value="";
    document.getElementById("cardLast4").value="";
    document.getElementById("customerName").value="";
    selectedCardType='';selectedUpiApp='';
    renderCart();renderInventory();
}

function showBillModal(data){
    const modal=document.getElementById("billModal");
    const content=document.getElementById("billContent");
    const payIcons={cash:'💵',card:'💳',upi:'📱'};
    const change=data.cashReceived>0?Math.max(0,data.cashReceived-data.total):0;
    content.innerHTML=`
        ${data.customerName?`<div style="margin-bottom:12px;font-weight:700;color:var(--primary);">👤 Customer: ${data.customerName}</div>`:''}
        ${data.items.map(c=>`
            <div class="bill-row">
                <div style="flex:1;">
                    <div style="font-weight:700;">${c.name} × ${c.qty}</div>
                    <div class="bill-item-sub">${c.brand?`🏷️ ${c.brand}`:''} ${c.pack?`· 📦 ${c.pack}${c.unit}`:''}· MRP: ₹${c.actual.toFixed(2)} · GST: ${c.gst}%</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:800;color:var(--primary);">₹${(c.fresh*c.qty).toFixed(2)}</div>
                    <div style="font-size:11px;color:var(--text-light);">₹${c.fresh.toFixed(2)} each</div>
                </div>
            </div>`).join("")}
        <div class="bill-total-row"><span>TOTAL</span><span>₹${data.total.toFixed(2)}</span></div>
        <div class="bill-payment-info">
            ${payIcons[data.paymentMethod]||'💵'} <b>${capitalize(data.paymentMethod)}</b>
            ${data.paymentRef?`<br><small>${data.paymentRef}</small>`:''}
            ${data.cashReceived>0?`<br>💰 Change: ₹${change.toFixed(2)}`:''}
        </div>
        <div style="text-align:center;margin-top:12px;font-size:12px;color:var(--text-light);">📅 ${data.time}<br>Thank you for shopping at FreshMart! 🛒</div>`;
    modal.classList.add("show");
}

function closeBillModal(){document.getElementById("billModal").classList.remove("show");}
function printBill(){window.print();}

// ============================================================
// SALES HISTORY
// ============================================================
function renderSales(filteredSales){
    const salesList=document.getElementById("salesList");
    const list=filteredSales||sales;
    if(list.length===0){salesList.innerHTML='<div class="card" style="text-align:center;padding:40px;color:var(--text-light);">📭 No sales recorded yet.</div>';return;}
    const payIcons={cash:'💵',card:'💳',upi:'📱'};
    const payClass={cash:'pay-cash',card:'pay-card',upi:'pay-upi'};
    salesList.innerHTML=list.map(s=>`
        <div class="sale-record">
            <div class="sale-record-header">
                <div>
                    <span class="sale-date">📅 ${s.time}</span>
                    <span class="payment-chip ${payClass[s.paymentMethod]||'pay-cash'}">${payIcons[s.paymentMethod]||'💵'} ${capitalize(s.paymentMethod||'cash')}</span>
                    ${s.customerName?`<span style="font-size:12px;color:var(--text-light);margin-left:8px;">👤 ${s.customerName}</span>`:''}
                </div>
                <span class="sale-total">₹${s.total.toFixed(2)}</span>
            </div>
            ${s.items.map(item=>`<div class="sale-item-detail"><b>${item.name}</b>${item.brand?` · 🏷️ ${item.brand}`:''}${item.pack?` · 📦 ${item.pack}${item.unit}`:''}(Qty: ${item.qty}) — ₹${item.fresh.toFixed(2)} each · MRP: ₹${item.actual.toFixed(2)} · GST: ${item.gst}%</div>`).join("")}
            ${s.paymentRef?`<div style="font-size:12px;color:var(--text-light);margin-top:6px;padding-left:10px;">📝 ${s.paymentRef}</div>`:''}
        </div>`).join("");
    const totalRev=list.reduce((s,i)=>s+i.total,0);
    const totalItems=list.reduce((s,i)=>s+i.items.reduce((a,b)=>a+b.qty,0),0);
    const today=new Date().toISOString().slice(0,10);
    const todayRev=list.filter(s=>s.time.startsWith(today)).reduce((s,i)=>s+i.total,0);
    document.getElementById("sumTotalBills").textContent=list.length;
    document.getElementById("sumRevenue").textContent="₹"+totalRev.toFixed(0);
    document.getElementById("sumItemsSold").textContent=totalItems;
    document.getElementById("sumToday").textContent="₹"+todayRev.toFixed(0);
}

function applySalesFilter(){
    const from=document.getElementById("filterFrom").value;
    const to=document.getElementById("filterTo").value;
    const payment=document.getElementById("filterPayment").value;
    const search=document.getElementById("salesSearch").value.toLowerCase();
    let filtered=sales.filter(s=>{
        const date=s.time.slice(0,10);
        if(from&&date<from) return false;
        if(to&&date>to) return false;
        if(payment&&s.paymentMethod!==payment) return false;
        if(search){
            const matchItem=s.items.some(i=>i.name.toLowerCase().includes(search));
            const matchCustomer=(s.customerName||'').toLowerCase().includes(search);
            if(!matchItem&&!matchCustomer) return false;
        }
        return true;
    });
    renderSales(filtered);
}

function resetSalesFilter(){
    document.getElementById("filterFrom").value="";
    document.getElementById("filterTo").value="";
    document.getElementById("filterPayment").value="";
    document.getElementById("salesSearch").value="";
    renderSales();
}

function exportCSV(){
    if(sales.length===0){showToast("No sales data","warning");return;}
    let csv="Date,Time,Customer,Product Name,Brand,Pack,Qty,Unit,MRP,GST,FreshMart Price,Total,Payment Method,Payment Ref\r\n";
    sales.forEach(s=>{
        const dt=s.time.split(" ");
        s.items.forEach(item=>{
            csv+=`"${dt[0]}","${dt[1]||''}","${s.customerName||''}","${item.name}","${item.brand||''}","${item.pack||''}","${item.qty}","${item.unit||''}","${item.actual}","${item.gst}","${item.fresh}","${s.total}","${s.paymentMethod||'cash'}","${s.paymentRef||''}"\r\n`;
        });
    });
    downloadFile(csv,"sales_report.csv","text/csv");
}

function downloadFile(content,filename,type){
    let blob=new Blob([content],{type});
    let link=document.createElement("a");
    link.href=URL.createObjectURL(blob);
    link.download=filename;
    link.click();
}

// ============================================================
// PAYMENT HISTORY
// ============================================================
function renderPaymentHistory(){
    fetch("index.php?action=get_payment")
    .then(res=>res.text())
    .then(text=>{
        try{
            const data=JSON.parse(text);
            payments=data.map(p=>({
                id:p.id,billId:p.bill_id,
                time:p.date+" "+p.time,date:p.date,
                customerName:p.customer_name||'',
                paymentMethod:p.payment_method||'cash',
                paymentRef:p.payment_ref||'',
                amount:parseFloat(p.amount),
                itemsList:p.items_list||''
            }));
            applyPaymentFilter();
        }catch(e){showToast("Error loading payments","error");}
    })
    .catch(err=>showToast("Network error: "+err,"error"));
}

function applyPaymentFilter(){
    const method=document.getElementById("payFilterMethod").value;
    const from=document.getElementById("payFilterFrom").value;
    const to=document.getElementById("payFilterTo").value;
    let filtered=payments.filter(p=>{
        if(method&&p.paymentMethod!==method) return false;
        if(from&&p.date<from) return false;
        if(to&&p.date>to) return false;
        return true;
    });
    const cashS=payments.filter(p=>p.paymentMethod==='cash');
    const cardS=payments.filter(p=>p.paymentMethod==='card');
    const upiS=payments.filter(p=>p.paymentMethod==='upi');
    document.getElementById("cashTotal").textContent="₹"+cashS.reduce((a,p)=>a+p.amount,0).toFixed(0);
    document.getElementById("cashCount").textContent=cashS.length+" transactions";
    document.getElementById("cardTotal").textContent="₹"+cardS.reduce((a,p)=>a+p.amount,0).toFixed(0);
    document.getElementById("cardCount").textContent=cardS.length+" transactions";
    document.getElementById("upiTotal").textContent="₹"+upiS.reduce((a,p)=>a+p.amount,0).toFixed(0);
    document.getElementById("upiCount").textContent=upiS.length+" transactions";
    const payIcons={cash:'💵',card:'💳',upi:'📱'};
    const payClass={cash:'pay-cash',card:'pay-card',upi:'pay-upi'};
    const tbody=document.getElementById("paymentTableBody");
    if(filtered.length===0){
        tbody.innerHTML=`<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light);">📭 No transactions found.</td></tr>`;
        return;
    }
    tbody.innerHTML=filtered.map((p,i)=>{
        let itemsDisplay='—';
        if(p.itemsList){
            itemsDisplay=p.itemsList.split(';;').map(seg=>{
                const parts=seg.split('|');
                return `${parts[0]||''}${parts[2]?' ('+parts[2]+')':''} ×${parts[1]||''}${parts[3]?' ['+parts[3]+']':''}`;
            }).join('<br>');
        }
        return `<tr>
            <td>${i+1}</td>
            <td style="font-size:12px;">${p.billId||'—'}</td>
            <td style="font-size:12px;">${p.time}</td>
            <td>${p.customerName||'—'}</td>
            <td style="font-size:12px;">${itemsDisplay}</td>
            <td><span class="payment-chip ${payClass[p.paymentMethod]||'pay-cash'}" style="font-size:12px;">${payIcons[p.paymentMethod]||'💵'} ${capitalize(p.paymentMethod||'cash')}</span></td>
            <td style="font-size:12px;color:var(--text-light);">${p.paymentRef||'—'}</td>
            <td style="font-weight:700;color:var(--primary);">₹${p.amount.toFixed(2)}</td>
        </tr>`;
    }).join("");
}

function resetPayFilter(){
    document.getElementById("payFilterMethod").value="";
    document.getElementById("payFilterFrom").value="";
    document.getElementById("payFilterTo").value="";
    applyPaymentFilter();
}

function exportPaymentCSV(){
    let csv="Bill ID,Date,Time,Customer,Payment Method,Reference,Amount\r\n";
    payments.forEach(p=>{
        const dt=(p.time||'').split(" ");
        csv+=`"${p.billId||''}","${dt[0]||''}","${dt[1]||''}","${p.customerName||''}","${p.paymentMethod||'cash'}","${p.paymentRef||''}","${p.amount.toFixed(2)}"\r\n`;
    });
    downloadFile(csv,"payment_history.csv","text/csv");
}

// ============================================================
// LOGIN / NAV
// ============================================================
function login(){
    const user=document.getElementById("username").value;
    const pass=document.getElementById("password").value;
    if(user==="admin"&&pass==="1234"){role="admin";document.getElementById("loginPage").style.display="none";document.getElementById("freshmartPage").style.display="flex";document.getElementById("dashUserInfo").textContent="👑 Admin";}
    else if(user==="cashier"&&pass==="1234"){role="cashier";document.getElementById("loginPage").style.display="none";document.getElementById("freshmartPage").style.display="flex";document.getElementById("dashUserInfo").textContent="💼 Cashier";}
    else{showToast("❌ Invalid username or password","error");}
}

function enterDashboard(){
    document.getElementById("freshmartPage").style.display="none";
    document.getElementById("dashboard").style.display="block";
    if(role==="cashier") document.getElementById("inventoryCard").style.display="none";
}

function logout(){
    role="";
    document.getElementById("dashboard").style.display="none";
    document.getElementById("loginPage").style.display="flex";
    document.getElementById("username").value="";
    document.getElementById("password").value="";
}

function showPage(id){
    document.querySelectorAll(".page").forEach(p=>p.classList.remove("active"));
    document.getElementById("dashboard").style.display="none";
    document.getElementById(id).classList.add("active");
    if(id==="inventory"){renderInventory();checkLowStock();}
    if(id==="sales") renderSales();
    if(id==="salesGraph") renderSalesGraphs();
    if(id==="paymentHistory") renderPaymentHistory();
}

function goBack(){
    document.querySelectorAll(".page").forEach(p=>p.classList.remove("active"));
    document.getElementById("dashboard").style.display="block";
}
function backToDashboard(){goBack();}

// ============================================================
// FIX 3: SALES GRAPHS — fixed data flow
// ============================================================
function renderSalesGraphs(){
    fetch("index.php?action=get_sales")
    .then(res=>res.json())
    .then(data=>{
        salesData=data.map(item=>({
            product_name: item.product_name,
            qty: parseInt(item.qty),
            total: parseFloat(item.total),
            fresh_price: parseFloat(item.fresh_price),
            actual_price: parseFloat(item.actual_price),
            gst: parseFloat(item.gst),
            date: item.date,
            time: item.time,
            bill_id: item.bill_id,
            payment_method: item.payment_method||'cash'
        }));
        sales = groupSalesByBill(data);
        drawAllCharts();
    })
    .catch(err=>showToast("Error loading graph data: "+err,"error"));
}

function drawAllCharts(){
    renderTimelineChart(currentGraphMode);
    renderTopProductsChart();
    renderPaymentMethodChart();
    renderTopProductsList();
}

function switchGraphTab(mode,element){
    currentGraphMode=mode;
    document.querySelectorAll('.graph-tab').forEach(t=>t.classList.remove('active'));
    element.classList.add('active');
    renderTimelineChart(mode);
}

function renderTimelineChart(mode){
    const ctx=document.getElementById('salesTimelineChart').getContext('2d');
    if(salesTimelineChart){salesTimelineChart.destroy();salesTimelineChart=null;}
    const aggr=aggregateSalesByTime(mode);

    if(aggr.labels.length===0){
        ctx.canvas.parentElement.insertAdjacentHTML('beforeend','<p style="text-align:center;color:#999;padding:20px;">No sales data available yet.</p>');
        return;
    }

    const colors={day:'rgba(39,174,96,0.8)',month:'rgba(52,152,219,0.8)',year:'rgba(155,89,182,0.8)'};
    const borders={day:'rgba(39,174,96,1)',month:'rgba(52,152,219,1)',year:'rgba(155,89,182,1)'};
    salesTimelineChart=new Chart(ctx,{
        type:'bar',
        data:{labels:aggr.labels,datasets:[{
            label:`Sales Qty by ${capitalize(mode)}`,
            data:aggr.quantities,
            backgroundColor:colors[mode],
            borderColor:borders[mode],
            borderWidth:2,borderRadius:6
        }]},
        options:{responsive:true,plugins:{legend:{display:true,position:'top'}},scales:{y:{beginAtZero:true,title:{display:true,text:'Quantity Sold'}},x:{title:{display:true,text:capitalize(mode)}}}}
    });
}

function aggregateSalesByTime(mode){
    const salesMap={};
    // Use unique bill IDs to avoid double-counting totals
    const seenBills={};
    
    salesData.forEach(sale=>{
        if(!sale.date) return;
        const date=new Date(sale.date);
        let key;
        if(mode==='day') key=sale.date;
        else if(mode==='month') key=`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}`;
        else key=`${date.getFullYear()}`;
        
        if(!salesMap[key]) salesMap[key]={qty:0, total:0};
        salesMap[key].qty += parseInt(sale.qty)||0;
        
        // Only count bill total once per bill
        if(!seenBills[sale.bill_id]){
            seenBills[sale.bill_id]=true;
            salesMap[key].total += parseFloat(sale.total)||0;
        }
    });
    
    const sortedKeys=Object.keys(salesMap).sort();
    const labels=sortedKeys.map(key=>{
        if(mode==='day'){const d=new Date(key);return d.toLocaleDateString('en-IN',{day:'2-digit',month:'short'});}
        else if(mode==='month'){const[y,m]=key.split('-');const d=new Date(y,m-1);return d.toLocaleDateString('en-IN',{month:'short',year:'numeric'});}
        return key;
    });
    return{labels, quantities:sortedKeys.map(k=>salesMap[k].qty), totals:sortedKeys.map(k=>salesMap[k].total)};
}

function renderTopProductsChart(){
    const ctx=document.getElementById('topProductsChart').getContext('2d');
    if(topProductsChart){topProductsChart.destroy();topProductsChart=null;}
    const productStats=getTopProducts();
    const top10=productStats.slice(0,10);
    if(top10.length===0) return;
    const bgColors=['rgba(231,76,60,0.8)','rgba(230,126,34,0.8)','rgba(241,196,15,0.8)','rgba(46,204,113,0.8)','rgba(26,188,156,0.8)','rgba(52,152,219,0.8)','rgba(155,89,182,0.8)','rgba(52,73,94,0.8)','rgba(149,165,166,0.8)','rgba(127,140,141,0.8)'];
    topProductsChart=new Chart(ctx,{
        type:'doughnut',
        data:{labels:top10.map(p=>p.name),datasets:[{data:top10.map(p=>p.qty),backgroundColor:bgColors,borderWidth:2,borderColor:'#fff'}]},
        options:{responsive:true,plugins:{legend:{position:'right'}}}
    });
}

function renderPaymentMethodChart(){
    const ctx=document.getElementById('paymentMethodChart').getContext('2d');
    if(paymentMethodChart){paymentMethodChart.destroy();paymentMethodChart=null;}
    const cashRev=sales.filter(s=>s.paymentMethod==='cash'||!s.paymentMethod).reduce((a,s)=>a+s.total,0);
    const cardRev=sales.filter(s=>s.paymentMethod==='card').reduce((a,s)=>a+s.total,0);
    const upiRev=sales.filter(s=>s.paymentMethod==='upi').reduce((a,s)=>a+s.total,0);
    paymentMethodChart=new Chart(ctx,{
        type:'bar',
        data:{labels:['💵 Cash','💳 Card','📱 UPI'],datasets:[{
            label:'Revenue (₹)',
            data:[cashRev,cardRev,upiRev],
            backgroundColor:['rgba(39,174,96,0.8)','rgba(52,152,219,0.8)','rgba(155,89,182,0.8)'],
            borderColor:['rgba(39,174,96,1)','rgba(52,152,219,1)','rgba(155,89,182,1)'],
            borderWidth:2,borderRadius:8
        }]},
        options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,title:{display:true,text:'Amount (₹)'}}}}
    });
}

function getTopProducts(){
    const productMap={};
    salesData.forEach(sale=>{
        const name=sale.product_name;
        if(!productMap[name]) productMap[name]={name,qty:0,revenue:0};
        productMap[name].qty+=parseInt(sale.qty)||0;
        productMap[name].revenue+=(parseFloat(sale.fresh_price)||0)*parseInt(sale.qty||0);
    });
    return Object.values(productMap).sort((a,b)=>b.qty-a.qty);
}

function renderTopProductsList(){
    const container=document.getElementById('topProductsList');
    const topProducts=getTopProducts().slice(0,10);
    if(topProducts.length===0){container.innerHTML='<p style="color:var(--text-light);text-align:center;padding:20px;">No sales data yet. Generate some bills first!</p>';return;}
    const medals=['🥇','🥈','🥉'];
    container.innerHTML=topProducts.map((p,index)=>`
        <div class="top-product-item">
            <span class="top-product-rank">${index<3?medals[index]:(index+1)}</span>
            <div><div class="top-product-name">${p.name}</div><div class="top-product-stats">Revenue: ₹${p.revenue.toFixed(2)}</div></div>
            <div class="top-product-qty">${p.qty} sold</div>
        </div>`).join('');
}
</script>
</body>
</html>