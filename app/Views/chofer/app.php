<?php /** App del chofer (PWA). */ ?>
<div x-data="appChofer" class="app-movil" x-cloak>
    <header class="app-cabecera">
        <button class="btn btn-topbar" type="button" x-show="vista !== 'inicio'" @click="history.back()" aria-label="Volver"><i class="bi bi-arrow-left"></i></button>
        <img src="<?= asset('assets/img/logo.svg') ?>" alt="" width="30" height="30" x-show="vista === 'inicio'" style="background:#fff;border-radius:50%;padding:2px">
        <h1 x-text="{ inicio: 'Mis entregas de hoy', entregas: 'Paradas', parada: parada ? parada.cliente.n : 'Entrega', mas: 'Más opciones' }[vista]"></h1>
        <button class="btn btn-topbar" type="button" @click="sincronizar()" aria-label="Sincronizar"><i class="bi" :class="sincronizando ? 'bi-arrow-repeat' : 'bi-cloud-arrow-up'"></i></button>
    </header>
    <button type="button" class="barra-estado" :class="estadoConexion().clase" @click="sesionVencida ? location.href = DT.url('/login') : sincronizar()">
        <i class="bi" :class="!online ? 'bi-wifi-off' : (sincronizando ? 'bi-arrow-repeat' : (pendientes() ? 'bi-cloud-upload' : 'bi-check-circle'))"></i>
        <span x-text="estadoConexion().texto"></span>
    </button>

    <template x-if="!datos"><div class="vacio-movil"><i class="bi bi-cloud-download"></i><p x-text="online ? 'Descargando tus entregas…' : 'Sin datos guardados. Conectate una vez para descargar la hoja de ruta.'"></p></div></template>

    <template x-if="datos">
    <div>
        <template x-for="o in conflictos()" :key="o.uuid">
            <div class="alert alert-warning m-2 py-2 small d-flex justify-content-between align-items-center gap-2">
                <span><i class="bi bi-exclamation-triangle"></i> <span x-text="(o.respuesta && o.respuesta.mensaje) || 'No se pudo registrar una entrega.'"></span></span>
                <button class="btn btn-sm btn-outline-dark" @click="descartarOp(o)">Entendido</button>
            </div>
        </template>

        <!-- ===================== INICIO ===================== -->
        <template x-if="vista === 'inicio'">
        <section class="app-seccion">
            <template x-if="!hojaActual()"><div class="vacio-movil"><i class="bi bi-truck"></i>No tenés hojas de ruta asignadas para hoy.</div></template>
            <template x-if="hojaActual()">
            <div x-data="{ r: resumen() }" x-effect="r = resumen()">
                <div class="tarjeta">
                    <div class="d-flex justify-content-between"><strong x-text="'Hoja de ruta N° ' + hojaActual().numero"></strong><span class="chip" x-text="'Camión ' + hojaActual().patente"></span></div>
                    <div class="d-flex justify-content-between mt-2"><span x-text="r.hechas + ' de ' + r.total + ' paradas'"></span><strong x-text="r.porc + ' %'"></strong></div>
                    <div class="progreso-reparto mt-1"><div :style="'width:' + r.porc + '%'"></div></div>
                </div>
                <template x-if="!enReparto(hojaActual())">
                    <div class="tarjeta text-center">
                        <p class="mb-2">Cargá el camión con la hoja de carga y registrá la salida.</p>
                        <button class="btn btn-primary btn-lg w-100 btn-touch" @click="darSalida(hojaActual())"><i class="bi bi-truck"></i> Salir a reparto</button>
                    </div>
                </template>
                <div class="kpis-movil mb-3">
                    <div class="kpi-movil"><div class="e">Pendientes</div><div class="v" x-text="r.total - r.hechas"></div></div>
                    <div class="kpi-movil"><div class="e">A entregar</div><div class="v fs-5" x-text="DT.dinero(r.aEntregar)"></div></div>
                </div>
                <template x-if="enReparto(hojaActual()) && proxima()">
                    <div class="tarjeta border-primary" style="border-width:2px">
                        <div class="small text-body-secondary fw-semibold">PRÓXIMA PARADA</div>
                        <div class="d-flex gap-2 align-items-center mt-1"><div class="parada-num" x-text="proxima().orden"></div><div class="min-w-0"><div class="tarjeta-titulo" x-text="proxima().cliente.n"></div><div class="tarjeta-sub" x-text="proxima().cliente.dir"></div></div></div>
                        <div class="acciones-tarjeta">
                            <a class="btn btn-outline-primary" :href="mapa(proxima().cliente)" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right"></i> Cómo llegar</a>
                            <button class="btn btn-primary" @click="abrirParada(proxima())"><i class="bi bi-box-seam"></i> Entregar</button>
                        </div>
                    </div>
                </template>
                <template x-if="enReparto(hojaActual()) && !proxima()"><div class="vacio-movil"><i class="bi bi-flag-fill text-success"></i>¡Terminaste el recorrido! Volvé al depósito para la rendición.</div></template>
                <button class="btn btn-outline-primary btn-lg w-100 btn-touch" @click="ir('entregas')"><i class="bi bi-list-ol"></i> Ver todas las paradas</button>
            </div>
            </template>
        </section>
        </template>

        <!-- ===================== LISTA DE PARADAS ===================== -->
        <template x-if="vista === 'entregas'">
        <section class="app-seccion">
            <template x-for="pa in paradas()" :key="pa.id">
                <div class="tarjeta" :class="{ hecha: hecha(pa) }" @click="abrirParada(pa)" role="button">
                    <div class="d-flex gap-2 align-items-start">
                        <div class="parada-num" x-text="pa.orden"></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="tarjeta-titulo" x-text="pa.cliente.n"></div>
                            <div class="tarjeta-sub" x-text="pa.cliente.dir + (pa.cliente.hor ? ' · ' + pa.cliente.hor : '')"></div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="chip" :class="etiqueta(estadoParada(pa))[1]"><span x-text="etiqueta(estadoParada(pa))[0]"></span><i class="bi bi-cloud-upload ms-1" x-show="opDe(pa)"></i></span>
                                <strong x-text="DT.dinero(pa.total)"></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </section>
        </template>

        <!-- ===================== PARADA ===================== -->
        <template x-if="vista === 'parada' && parada">
        <section class="app-seccion">
            <div class="tarjeta">
                <div class="d-flex gap-2 align-items-center"><div class="parada-num" x-text="parada.orden"></div><div class="min-w-0"><div class="tarjeta-titulo" x-text="parada.cliente.n"></div><div class="tarjeta-sub" x-text="parada.cliente.t || ''"></div></div></div>
                <div class="mt-2" x-text="parada.cliente.dir + ', ' + (parada.cliente.loc || '')"></div>
                <div class="tarjeta-sub" x-text="'Pedido N° ' + parada.pedido + ' · ' + (parada.cliente.cp === 'contado' ? 'CONTADO' : 'Cuenta corriente') + (parada.cliente.hor ? ' · ' + parada.cliente.hor : '')"></div>
                <div class="mt-1" x-show="parada.cliente.saldo > 0"><span class="saldo-deuda" x-text="'Saldo anterior: ' + DT.dinero(parada.cliente.saldo)"></span></div>
                <div class="acciones-tarjeta">
                    <a class="btn btn-outline-primary" :href="mapa(parada.cliente)" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> Mapa</a>
                    <a class="btn btn-outline-secondary" :href="telefono(parada.cliente)" x-show="parada.cliente.tel"><i class="bi bi-telephone-fill"></i> Llamar</a>
                    <a class="btn btn-success" :href="whatsapp(parada.cliente, 'Hola, soy ' + datos.usuario + ' de ' + datos.empresa + ', estoy llegando con tu pedido.')" target="_blank" rel="noopener" x-show="parada.cliente.wa"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
            <div class="alert alert-info py-2" x-show="parada.obs"><i class="bi bi-chat-left-text"></i> <span x-text="parada.obs"></span></div>
            <template x-if="hecha(parada)">
                <div class="alert py-2" :class="['entregado'].includes(estadoParada(parada)) ? 'alert-success' : 'alert-warning'">
                    <strong x-text="etiqueta(estadoParada(parada))[0]"></strong><span x-show="opDe(parada)"> · pendiente de enviar</span><span x-show="parada.motivo" x-text="' · ' + parada.motivo"></span>
                </div>
            </template>
            <div class="tarjeta">
                <div class="d-flex justify-content-between mb-1"><strong>Mercadería</strong><strong x-text="DT.dinero(parada.total)"></strong></div>
                <template x-for="it in parada.items" :key="it.id">
                    <div class="d-flex justify-content-between border-top py-2 gap-2">
                        <span><span class="fw-semibold" x-text="DT.cant(it.cant) + ' ' + it.pres"></span> <span x-text="it.d"></span><small class="text-success" x-show="it.bon > 0" x-text="' +' + DT.cant(it.bon) + ' bonif.'"></small></span>
                        <span class="text-nowrap" x-text="DT.dinero(it.sub)"></span>
                    </div>
                </template>
            </div>
            <template x-if="!hecha(parada) && enReparto(hojaActual())">
                <div class="botonera-entrega">
                    <button class="btn btn-success" @click="iniciar('entregado')"><i class="bi bi-check2-circle"></i>Entregado</button>
                    <button class="btn btn-warning" @click="iniciar('parcial')"><i class="bi bi-pie-chart"></i>Parcial</button>
                    <button class="btn btn-outline-danger" @click="iniciar('rechazado')"><i class="bi bi-x-octagon"></i>Rechazado</button>
                    <button class="btn btn-outline-secondary" @click="iniciar('cerrado')"><i class="bi bi-door-closed"></i>Cerrado</button>
                </div>
            </template>
            <template x-if="!enReparto(hojaActual())"><p class="text-body-secondary text-center">Registrá la salida a reparto para cargar entregas.</p></template>
        </section>
        </template>

        <!-- ===================== MÁS ===================== -->
        <template x-if="vista === 'mas'">
        <section class="app-seccion">
            <div class="tarjeta" x-data="{ r: resumen() }" x-effect="r = resumen()">
                <strong>Cobrado y registrado en este celular (sin enviar)</strong>
                <template x-for="(v, k) in r.cobradoCelular"><div class="d-flex justify-content-between"><span x-text="datos.formas[k] || k"></span><strong x-text="DT.dinero(v)"></strong></div></template>
                <p class="small text-body-secondary mb-0" x-show="!Object.keys(r.cobradoCelular).length">Todo lo registrado ya se envió a la oficina.</p>
            </div>
            <div class="d-grid gap-2 lista-acciones-grandes">
                <button class="btn btn-outline-primary" @click="sincronizar()"><i class="bi bi-arrow-repeat"></i> Sincronizar ahora</button>
                <button class="btn btn-outline-primary" @click="descargar().then(() => DT.aviso('Datos actualizados', 'exito'))" :disabled="!online"><i class="bi bi-cloud-download"></i> Actualizar hoja de ruta</button>
                <button class="btn btn-outline-secondary" data-tema-alternar><i class="bi bi-circle-half"></i> Modo claro / oscuro</button>
                <button class="btn btn-outline-danger" @click="cerrarSesion()"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
            </div>
        </section>
        </template>
    </div>
    </template>

    <!-- ===================== HOJAS INFERIORES ===================== -->
    <template x-if="hoja"><div class="hoja-fondo" @click="hoja = null"></div></template>
    <template x-if="hoja && parada">
        <div class="hoja-inferior">
            <div class="d-flex justify-content-between align-items-center"><h3 class="mb-0" x-text="etiqueta(resultado)[0] + ' — ' + parada.cliente.n"></h3><button class="btn-close" @click="hoja = null" aria-label="Cerrar"></button></div>
            <template x-if="resultado === 'parcial'">
                <div class="mt-2">
                    <p class="small text-body-secondary mb-1">Indicá las unidades que el cliente recibió:</p>
                    <template x-for="it in parada.items" :key="it.id">
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">
                            <span class="small"><span class="fw-semibold" x-text="it.d"></span><br><span class="text-body-secondary" x-text="'Pedido: ' + DT.cant(it.base) + ' ' + it.u"></span></span>
                            <div class="stepper">
                                <button class="btn btn-outline-secondary" @click="sumarItem(it, -1)" aria-label="Restar">−</button>
                                <input type="text" class="form-control" inputmode="numeric" :value="entregados[it.id]" @change="entregados[it.id] = Math.min(it.base, Math.max(0, DT.parse($event.target.value))); recalcularCobro()" aria-label="Entregado">
                                <button class="btn btn-outline-secondary" @click="sumarItem(it, 1)" aria-label="Sumar">+</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="resultado === 'rechazado' || resultado === 'parcial'">
                <div class="mt-2">
                    <label class="form-label fw-semibold">Motivo</label>
                    <template x-for="(t, k) in datos.motivos" :key="k"><button class="btn opcion-grande" :class="motivo === k ? 'btn-primary' : 'btn-outline-secondary'" @click="motivo = k" x-text="t"></button></template>
                </div>
            </template>
            <template x-if="resultado === 'cerrado'"><p class="mt-2">El comercio estaba cerrado: el pedido vuelve al depósito y la oficina puede reprogramarlo.</p></template>
            <template x-if="resultado !== 'cerrado'">
                <div class="mt-3">
                    <div class="d-flex justify-content-between"><strong>Cobro</strong><span x-show="resultado !== 'rechazado'">A cobrar: <strong x-text="DT.dinero(aCobrar())"></strong></span></div>
                    <p class="small text-body-secondary mb-2" x-show="resultado === 'rechazado'">Si el cliente pagó deuda anterior, registralo acá.</p>
                    <template x-for="(p, k) in pagos" :key="k">
                        <div class="d-flex gap-2 mb-2">
                            <select class="form-select" x-model="p.forma" style="max-width:48%"><template x-for="(t, f) in datos.formas" :key="f"><option :value="f" x-text="t" :selected="p.forma === f"></option></template></select>
                            <input class="form-control text-end fw-bold" inputmode="decimal" x-model="p.importe" @input="p.auto = false" placeholder="$ 0" aria-label="Importe">
                            <button class="btn btn-outline-danger" @click="pagos.splice(k, 1)" aria-label="Quitar"><i class="bi bi-x"></i></button>
                        </div>
                    </template>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-secondary" @click="agregarPago('efectivo')">+ Efectivo</button>
                        <button class="btn btn-sm btn-outline-secondary" @click="agregarPago('transferencia')">+ Transferencia</button>
                        <button class="btn btn-sm btn-outline-secondary" @click="agregarPago('mercadopago')">+ Mercado Pago</button>
                    </div>
                    <p class="small mt-2 mb-0" x-show="resultado !== 'rechazado' && aCobrar() - totalPagos() > 0.009">Queda en cuenta corriente: <strong x-text="DT.dinero(aCobrar() - totalPagos())"></strong></p>
                </div>
            </template>
            <input class="form-control mt-3" x-model="obsEntrega" placeholder="Comentario (opcional)" maxlength="200">
            <button class="btn btn-lg w-100 btn-touch mt-3" :class="resultado === 'entregado' ? 'btn-success' : (resultado === 'parcial' ? 'btn-warning' : 'btn-danger')" @click="confirmar()"><i class="bi bi-check-lg"></i> Confirmar</button>
        </div>
    </template>

    <nav class="bottom-nav" aria-label="Navegación de la app">
        <a href="#" :class="{ active: vista === 'inicio' }" @click.prevent="ir('inicio')"><i class="bi bi-house"></i><span>Inicio</span></a>
        <a href="#" :class="{ active: vista === 'entregas' }" @click.prevent="ir('entregas')"><i class="bi bi-list-ol"></i><span>Mis entregas</span></a>
        <a href="#" class="nav-principal" @click.prevent="proxima() ? abrirParada(proxima()) : ir('inicio')"><i class="bi bi-geo-fill"></i><span>Próxima</span></a>
        <a href="#" @click.prevent="proxima() && window.open(mapa(proxima().cliente), '_blank')"><i class="bi bi-map"></i><span>Navegar</span></a>
        <a href="#" :class="{ active: vista === 'mas' }" @click.prevent="ir('mas')"><i class="bi bi-three-dots"></i><span>Más</span><span class="contador" x-show="pendientes()" x-text="pendientes()"></span></a>
    </nav>
    <form method="post" action="<?= url('/logout') ?>" id="formSalir" class="d-none"><input type="hidden" name="_token" value=""></form>
</div>
<?php App\Core\View::inicio('scripts'); ?><script src="<?= asset('assets/js/chofer.js') ?>"></script><?php App\Core\View::fin(); ?>
