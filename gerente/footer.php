<?php
$verGerenteJs = $verGerenteJs ?? filemtime(__DIR__ . '/gerente.js');
?>
    </main>

    <script src="../auth.js"></script>
    <script src="gerente.js?v=<?= $verGerenteJs ?>"></script>
</body>
</html>