<?php
include_once "includes/funciones.php";
verificar_permiso("mapa");
$puedeEditar = usuario_tiene_permiso("empleados");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa del local</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(3){background:#fff}.navigation ul li:nth-child(3) a{color:#001f47}.navigation ul li:nth-child(3) a .icon img{content:url('assets/img/sidebar/theme-map.svg')}
        .mapa-content{padding:20px 30px}.mapa-content h1{color:#001f47}.mapa-layout{display:grid;gap:20px;grid-template-columns:240px 1fr}.mapa-tools{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;height:max-content}.mapa-tools h2{color:#001f47;font-size:1.1rem;margin-top:0}.mapa-tools label{color:#001f47;display:block;font-weight:bold;margin:12px 0 5px}.mapa-tools input,.mapa-tools select{border:1px solid #bbb;border-radius:5px;padding:9px;width:100%}.mapa-tools button{background:#001f47;border:0;border-radius:5px;color:#fff;cursor:pointer;margin-top:16px;padding:10px;width:100%}.mapa-mensaje{font-size:.85rem;margin-top:12px}.mapa-superficie{aspect-ratio:1200/700;background-color:#dce4e2;background-image:linear-gradient(rgba(0,31,71,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(0,31,71,.08) 1px,transparent 1px);background-size:3.333% 5.714%;border:2px solid #78908b;border-radius:8px;overflow:hidden;position:relative;width:100%}.mapa-superficie::before{color:rgba(0,31,71,.35);content:'Fondo provisional: reemplazar por la imagen del local';font-size:1.1rem;left:50%;pointer-events:none;position:absolute;text-align:center;top:50%;transform:translate(-50%,-50%);width:260px}.mesa{align-items:center;background:#fff;border:3px solid #245b4a;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,.18);cursor:grab;display:flex;flex-direction:column;justify-content:center;min-height:60px;position:absolute;touch-action:none;user-select:none;z-index:1}.mesa:active{cursor:grabbing}.mesa strong{color:#001f47}.mesa small{font-size:.7rem}.mesa button{background:#fff;border:0;color:#b42318;cursor:pointer;font-size:.7rem}.mesa.libre{border-color:#27824b}.mesa.ocupada{border-color:#bd3d3d}.mesa.paralimpiar{border-color:#d99018}@media(max-width:900px){.mapa-layout{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>
    <div class="main"><div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once 'includes/profile.php'; ?></div>
        <main class="mapa-content"><h1>Mapa del local</h1><div class="mapa-layout">
            <?php if ($puedeEditar): ?><aside class="mapa-tools"><h2>Nueva mesa</h2><form id="formMesa"><label for="numero">Número</label><input id="numero" type="number" min="1" required><label for="ancho">Ancho</label><input id="ancho" type="number" min="60" value="100" required><button type="submit">Crear mesa</button></form><p id="mensajeMapa" class="mapa-mensaje"></p><small>Las mesas nuevas se crean como Libres. Arrastra una mesa para cambiar su posición X/Y.</small></aside><?php endif; ?>
            <section id="superficie" class="mapa-superficie" aria-label="Plano del local"></section>
        </div></main>
    </div>
<script>
const puedeEditar = <?php echo $puedeEditar ? "true" : "false"; ?>;
let estados = [];
let mesas = [];
const superficie = document.getElementById("superficie");
document.addEventListener("DOMContentLoaded", cargarMapa);
async function cargarMapa() { const respuesta=await fetch("apis/api_mesas.php"); const contenido=await respuesta.text(); let datos; try { datos=JSON.parse(contenido); } catch (error) { mostrarMensaje("La API del mapa devolvió una respuesta inválida.",true); console.error(contenido); return; } if(!respuesta.ok){mostrarMensaje(datos.error || "No se pudo cargar el mapa.",true);return;} mesas=datos.mesas; estados=datos.estados; renderMesas(); }
function renderMesas(){ superficie.querySelectorAll(".mesa").forEach(m=>m.remove()); mesas.forEach(mesa=>{const elemento=document.createElement("article");const estado=(mesa.estado||"").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"");elemento.className=`mesa ${estado}`;elemento.style.left=`${Math.max(0,Number(mesa.pos_x)||0)/1200*100}%`;elemento.style.top=`${Math.max(0,Number(mesa.pos_y)||0)/700*100}%`;elemento.style.width=`${Math.max(60,Number(mesa.ancho)||100)/1200*100}%`;elemento.dataset.id=mesa.id_mesa;elemento.innerHTML=`<strong>Mesa ${escapeHTML(mesa.numero)}</strong><small>${escapeHTML(mesa.estado)}</small>${puedeEditar?`<button type="button" onpointerdown="event.stopPropagation()" onclick="eliminarMesa(event,${mesa.id_mesa})">Eliminar</button>`:""}`;if(puedeEditar) activarArrastre(elemento,mesa);superficie.appendChild(elemento);});}
function activarArrastre(elemento,mesa){let inicio=null;elemento.addEventListener("pointerdown",evento=>{if(evento.target.closest("button"))return;const rect=superficie.getBoundingClientRect();inicio={x:evento.clientX,y:evento.clientY,left:Number(mesa.pos_x)||0,top:Number(mesa.pos_y)||0,ancho:rect.width,alto:rect.height};elemento.setPointerCapture(evento.pointerId);});elemento.addEventListener("pointermove",evento=>{if(!inicio)return;mesa.pos_x=Math.max(0,Math.min(1200-(Number(mesa.ancho)||100),Math.round(inicio.left+(evento.clientX-inicio.x)/inicio.ancho*1200)));mesa.pos_y=Math.max(0,Math.min(700-60,Math.round(inicio.top+(evento.clientY-inicio.y)/inicio.alto*700)));elemento.style.left=`${mesa.pos_x/1200*100}%`;elemento.style.top=`${mesa.pos_y/700*100}%`;});elemento.addEventListener("pointerup",async()=>{if(!inicio)return;inicio=null;await guardarMesa(mesa);});}
async function eliminarMesa(evento,id){evento.stopPropagation();if(!confirm("¿Eliminar esta mesa?"))return;const respuesta=await fetch("apis/api_mesas.php",{method:"DELETE",headers:{"Content-Type":"application/json"},body:JSON.stringify({id_mesa:id})});const datos=await respuesta.json();if(!respuesta.ok){mostrarMensaje(datos.error||"No se pudo eliminar la mesa.",true);return;}mostrarMensaje("Mesa eliminada.",false);cargarMapa();}
document.getElementById("formMesa")?.addEventListener("submit",async evento=>{evento.preventDefault();const respuesta=await fetch("apis/api_mesas.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({numero:Number(document.getElementById("numero").value),pos_x:20,pos_y:20,ancho:Number(document.getElementById("ancho").value)})});const datos=await respuesta.json();if(!respuesta.ok){mostrarMensaje(datos.error,true);return;}mostrarMensaje("Mesa creada como Libre.",false);document.getElementById("formMesa").reset();document.getElementById("ancho").value=100;cargarMapa();});
async function guardarMesa(mesa){const respuesta=await fetch("apis/api_mesas.php",{method:"PATCH",headers:{"Content-Type":"application/json"},body:JSON.stringify({id_mesa:Number(mesa.id_mesa),numero:Number(mesa.numero),pos_x:Number(mesa.pos_x),pos_y:Number(mesa.pos_y),ancho:Number(mesa.ancho),id_estado:Number(mesa.id_estado)})});if(!respuesta.ok)mostrarMensaje("No se pudo guardar la posición.",true);}
function mostrarMensaje(texto,error){const elemento=document.getElementById("mensajeMapa");if(elemento){elemento.textContent=texto;elemento.style.color=error?"#b42318":"#16723b";}}
function escapeHTML(valor){if(valor===null||valor===undefined)return "";return String(valor).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;").replace(/'/g,"&#039;");}
</script>
</body>
</html>
