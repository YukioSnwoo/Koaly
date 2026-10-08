<?php
$verCore        = filemtime(__DIR__ . '/js/core.js');
$verComponentes = filemtime(__DIR__ . '/js/componentes.js');
$verPaginas     = filemtime(__DIR__ . '/js/paginas.js');
?>
</main>

<script src="../auth.js"></script>
<script src="js/core.js?v=<?= $verCore ?>"></script>
<script src="js/componentes.js?v=<?= $verComponentes ?>"></script>
<script src="js/paginas.js?v=<?= $verPaginas ?>"></script>
</body>
</html>
