</div>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>

<script src="/assets/js/sweetalert2.all.min.js"></script>

<script>

const Toast = Swal.mixin({
toast: true,
position: 'top-end',
showConfirmButton: false,
timer: 2500,
timerProgressBar: true
});

</script>

<?php if(isset($_SESSION['toast'])): ?>

<script>

Toast.fire({
icon: 'success',
title: <?= json_encode($_SESSION['toast']) ?>
});

</script>

<?php unset($_SESSION['toast']); endif; ?>


<script>

document.addEventListener("DOMContentLoaded", function(){

const menus = document.querySelectorAll(".menu-title");

menus.forEach(function(menu){

menu.addEventListener("click", function(){

const parent = this.parentElement;

document.querySelectorAll(".menu-group").forEach(function(item){

if(item !== parent){
item.classList.remove("active");
}

});

parent.classList.toggle("active");

});

});

});

</script>

</body>
</html>
