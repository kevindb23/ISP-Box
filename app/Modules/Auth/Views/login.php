<?php

$branding = require BASE_PATH . '/config/branding.php';
$app = require BASE_PATH . '/config/app.php';

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($branding['client_name']) ?></title>

<link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">

<style>

body{
background:#eef1f5;
margin:0;
font-family:system-ui;
}

.login-page{
height:100vh;
display:flex;
align-items:center;
justify-content:center;
}

.login-wrapper{
text-align:center;
}

.login-icon{
width:64px;
height:64px;
background:<?= $branding['primary_color'] ?>;
border-radius:14px;
display:flex;
align-items:center;
justify-content:center;
margin:auto;
color:white;
font-size:28px;
margin-bottom:16px;
box-shadow:0 10px 20px rgba(0,0,0,0.15);
}

.login-title{
font-size:28px;
font-weight:700;
color:#111827;
margin-bottom:6px;
}

.login-subtitle{
font-size:14px;
color:#6b7280;
margin-bottom:26px;
}

.login-card{
width:420px;
border-radius:12px;
border:none;
box-shadow:0 20px 40px rgba(0,0,0,0.12);
}

.form-control{
height:46px;
padding-left:42px;
}

.input-group-wrapper{
position:relative;
}

.input-icon{
position:absolute;
left:12px;
top:13px;
color:#9ca3af;
}

.password-toggle{
position:absolute;
right:12px;
top:13px;
cursor:pointer;
color:#9ca3af;
}

.btn-login{
height:46px;
font-weight:500;
background:<?= $branding['primary_color'] ?>;
border:none;
}

.btn-login:hover{
opacity:.9;
}

.powered-by{
margin-top:22px;
font-size:13px;
color:#9ca3af;
}

.powered-by strong{
color:#111827;
font-weight:600;
}

.toast-container{
position:fixed;
top:20px;
right:20px;
z-index:9999;
}

.toast-card{
display:flex;
align-items:center;
gap:10px;
background:#fff;
padding:10px 14px;
border-radius:8px;
box-shadow:0 8px 18px rgba(0,0,0,.15);
font-size:14px;
animation:slideIn .25s ease;
}

.toast-icon{
color:#22c55e;
font-size:18px;
}

@keyframes slideIn{
from{
transform:translateX(100%);
opacity:0;
}
to{
transform:translateX(0);
opacity:1;
}
}

</style>

</head>

<body>

<div class="toast-container" id="toastContainer"></div>

<div class="login-page">

<div class="login-wrapper">

<div class="login-icon">
<i class="bi bi-gear"></i>
</div>

<div class="login-title">
ISP-in-a-Box
</div>

<div class="card login-card">

<div class="card-body p-4">

<form id="loginForm">

<input type="hidden" name="csrf_token" value="<?= \App\Core\Security\Csrf::token() ?>">

<div class="mb-3 text-start">

<label class="form-label">Username</label>

<div class="input-group-wrapper">

<i class="bi bi-person input-icon"></i>

<input
type="text"
name="username"
class="form-control"
placeholder="Enter your username"
required>

</div>

</div>

<div class="mb-3 text-start">

<label class="form-label">Password</label>

<div class="input-group-wrapper">

<i class="bi bi-lock input-icon"></i>

<input
type="password"
name="password"
id="password"
class="form-control"
placeholder="Enter your password"
required>

<i class="bi bi-eye password-toggle" id="togglePassword"></i>

</div>

</div>

<button class="btn btn-primary w-100 btn-login" id="loginBtn">
Sign in
</button>

</form>

</div>

</div>

<div class="powered-by">
Powered by <strong><?= defined('POWERED_BY') ? POWERED_BY : '1WAN' ?></strong>
</div>

</div>

</div>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/js/sweetalert2.all.min.js"></script>

<script>

const togglePassword=document.getElementById("togglePassword");
const password=document.getElementById("password");

togglePassword.onclick=()=>{

password.type=password.type==="password"?"text":"password";

togglePassword.classList.toggle("bi-eye");
togglePassword.classList.toggle("bi-eye-slash");

};

function showToast(message){

const container=document.getElementById("toastContainer");

const toast=document.createElement("div");

toast.className="toast-card";

toast.innerHTML=`
<div class="toast-icon">
<i class="bi bi-check-circle-fill"></i>
</div>
<div>${message}</div>
`;

container.appendChild(toast);

setTimeout(()=>toast.remove(),4000);

}

loginForm.addEventListener("submit",async(e)=>{

e.preventDefault();

loginBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Signing in...';

const formData=new FormData(loginForm);

const payload={
username:formData.get("username"),
password:formData.get("password"),
csrf_token:formData.get("csrf_token")
};

try{

const response=await fetch("/login",{
method:"POST",
headers:{
"Content-Type":"application/json"
},
body:JSON.stringify(payload)
});

const data=await response.json();

if(data.success){

showToast("Logged in successfully");

setTimeout(()=>{

Swal.fire({
icon:"success",
title:"Success!",
text:"Logged in successfully",
confirmButtonColor:"#3b82f6"
}).then(()=>{
window.location.href="/dashboard";
});

},400);

}else{

Swal.fire({
icon:"error",
title:"Login Failed",
text:data.message || "Invalid username or password"
});

loginBtn.innerHTML="Sign in";

}

}catch(err){

Swal.fire({
icon:"error",
title:"Server Error",
text:"Unable to process login"
});

loginBtn.innerHTML="Sign in";

}

});

</script>

</body>
</html>
