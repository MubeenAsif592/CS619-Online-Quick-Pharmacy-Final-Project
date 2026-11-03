<footer class="bg-dark text-white text-center py-3 mt-5 w-100">
    <div class="containter">
        <p clas="mb-0">&copy; <?=date('Y')?> Online Quick pharmacy Market. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded",function(){
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alertBox){
            setTimeout(function(){
                alertBox.style.transition = "opacity 0.5s ease";
                alertBox.style.opacity = "0";

                setTimeout(function(){
                    alertBox.style.display = "none";
                },500);
            },3000);
        });
    });
</script>