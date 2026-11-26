<?php
// Page එක තියෙන folder එක අනුව JS path එක auto සැකසේ
$js_path = file_exists('js/script.js') ? 'js/script.js' : '../js/script.js';
?>
<footer class="main-footer">
    <p>&copy; 2026 Diaz Pathi Systems. All rights Reserved.</p>
</footer>
<script src="<?php echo $js_path; ?>" defer></script>
</body>
</html>