<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Enviando…</title></head>
<body onload="document.forms[0].submit()">
<form method="POST" action="{{ $returnUrl }}">
    <input type="hidden" name="JWT" value="{{ $jwt }}">
    <noscript><button type="submit">Continuar</button></noscript>
</form>
</body></html>
