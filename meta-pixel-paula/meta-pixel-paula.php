<?php
/**
 * Plugin Name: Meta Pixel Paula Sampaio
 * Description: Pixel + CAPI server-side com deduplicação por event_id.
 * Version:     1.1.0
 * Author:      Agencia
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ═══════════════════════════════════════════════════════════════════════════
//  CHARSET UTF-8 — força em todos os níveis (evita acentos quebrados)
// ═══════════════════════════════════════════════════════════════════════════

// 1. Força a opção de charset do WordPress (afeta o header gerado pelo WP)
add_filter( 'option_blog_charset', 'ps_force_charset_option' );
function ps_force_charset_option() {
    return 'UTF-8';
}

// 2. Sobrescreve o header Content-Type com replace=true (prioridade 99)
add_action( 'send_headers', 'ps_set_charset_header', 99 );
function ps_set_charset_header() {
    header( 'Content-Type: text/html; charset=UTF-8', true );
}

// 3. Meta charset como primeiro elemento do <head>
add_action( 'wp_head', 'ps_charset_meta', 0 );
function ps_charset_meta() {
    echo '<meta charset="UTF-8">' . "\n";
}


// ─── Credenciais (token NUNCA chega ao browser) ──────────────────────────────
define( 'PS_PIXEL_ID',   '1646143013341739' );
define( 'PS_CAPI_TOKEN', 'EAAZAZBoDHAiAcBRvBA8YaelZAGRKJM7YZBjIOB1UkChb6PHYYkGU7ZCNwKhstQ3ZC6zUi5MKEsQwZBKs2fLxRq0eHCVICQnhrliieFxNUZAAsvcfIdQSTNcDlozXZAJf2GWuYAKZAI2vYZBGLlf4oZCAzZCrFJMX6dMY409wQyZAdZCABm3iluujWP6jaow2Y3RkK9zzwZDZD' );
// ─────────────────────────────────────────────────────────────────────────────


// ═══════════════════════════════════════════════════════════════════════════
//  PIXEL BASE CODE — injetado no <head> de todas as páginas do WP
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_head', 'ps_pixel_base_code', 1 );
function ps_pixel_base_code() { ?>
<!-- Meta Pixel – Dra. Paula Sampaio -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','<?php echo PS_PIXEL_ID; ?>');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=<?php echo PS_PIXEL_ID; ?>&ev=PageView&noscript=1"/></noscript>
<!-- / Meta Pixel -->
<?php }


// ═══════════════════════════════════════════════════════════════════════════
//  CAPI.JS INLINE — injetado no rodapé
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_footer', 'ps_capi_inline_script', 99 );
function ps_capi_inline_script() {
    $ajax = admin_url( 'admin-ajax.php' );
    ?>
<script>
(function(){
  var ajaxurl='<?php echo esc_js( $ajax ); ?>';

  async function sha256(s){
    var b=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(s.trim().toLowerCase()));
    return Array.from(new Uint8Array(b)).map(function(x){return x.toString(16).padStart(2,'0')}).join('');
  }
  function normPhone(p){
    var d=p.replace(/\D/g,'');
    if(d.length===10||d.length===11)d='55'+d;
    return d;
  }
  function genEid(pfx){return pfx+'-'+Date.now()+'-'+Math.random().toString(36).slice(2,9);}
  async function sendCapi(action,eid,ud){
    var b=new URLSearchParams({action:action,eid:eid,source_url:location.href});
    if(ud.em)b.append('em',ud.em);
    if(ud.ph)b.append('ph',ud.ph);
    try{await fetch(ajaxurl,{method:'POST',body:b});}catch(_){}
  }

  // PageView
  (async function(){
    var eid=genEid('pv');
    if(window.fbq)fbq('track','PageView',{},{eventID:eid});
    await sendCapi('ps_pageview',eid,{});
  })();

  // Lead — chamado após envio bem-sucedido do formulário
  window.paulaTrackLead=async function(){
    var eid=genEid('lead'),ud={};
    var eEl=document.getElementById('form-email')||document.querySelector('[name="email"]');
    var pEl=document.getElementById('form-whatsapp')||document.querySelector('[name="whatsapp"],[name="phone"],[name="telefone"]');
    if(eEl&&eEl.value.trim())ud.em=await sha256(eEl.value.trim());
    if(pEl&&pEl.value.trim())ud.ph=await sha256(normPhone(pEl.value.trim()));
    if(window.fbq)fbq('track','Lead',{},{eventID:eid});
    await sendCapi('ps_lead',eid,ud);
  };
})();
</script>
<?php }


// ═══════════════════════════════════════════════════════════════════════════
//  RATE LIMITING — máx. 30 requisições por IP a cada 60 segundos
// ═══════════════════════════════════════════════════════════════════════════

function ps_rate_limit() {
    $ip    = ps_get_ip();
    $key   = 'ps_rl_' . md5( $ip );
    $hits  = (int) get_transient( $key );
    $limit = 30;
    $window = 60;

    if ( $hits >= $limit ) {
        wp_send_json_error( [ 'code' => 'rate_limited' ], 429 );
        exit;
    }

    if ( $hits === 0 ) {
        set_transient( $key, 1, $window );
    } else {
        set_transient( $key, $hits + 1, $window );
    }
}

function ps_is_sha256( $value ) {
    return (bool) preg_match( '/^[a-f0-9]{64}$/', $value );
}


// ═══════════════════════════════════════════════════════════════════════════
//  PAGEVIEW CAPI
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_ps_pageview',        'ps_handle_pageview' );
add_action( 'wp_ajax_nopriv_ps_pageview', 'ps_handle_pageview' );
function ps_handle_pageview() {
    ps_rate_limit();

    $eid = isset( $_POST['eid'] )
        ? sanitize_text_field( wp_unslash( $_POST['eid'] ) )
        : 'pv-' . bin2hex( random_bytes(8) );

    $source_url = isset( $_POST['source_url'] )
        ? esc_url_raw( wp_unslash( $_POST['source_url'] ) )
        : ps_current_url();

    ps_capi_send( 'PageView', $eid, [], $source_url );
    wp_send_json_success();
}


// ═══════════════════════════════════════════════════════════════════════════
//  LEAD CAPI
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_ps_lead',        'ps_handle_lead' );
add_action( 'wp_ajax_nopriv_ps_lead', 'ps_handle_lead' );
function ps_handle_lead() {
    ps_rate_limit();

    $eid = isset( $_POST['eid'] )
        ? sanitize_text_field( wp_unslash( $_POST['eid'] ) )
        : 'lead-' . bin2hex( random_bytes(8) );

    $source_url = isset( $_POST['source_url'] )
        ? esc_url_raw( wp_unslash( $_POST['source_url'] ) )
        : ps_current_url();

    $user_data = [];
    if ( ! empty( $_POST['em'] ) ) {
        $em = sanitize_text_field( wp_unslash( $_POST['em'] ) );
        if ( ps_is_sha256( $em ) ) $user_data['em'] = [ $em ];
    }
    if ( ! empty( $_POST['ph'] ) ) {
        $ph = sanitize_text_field( wp_unslash( $_POST['ph'] ) );
        if ( ps_is_sha256( $ph ) ) $user_data['ph'] = [ $ph ];
    }

    ps_capi_send( 'Lead', $eid, [], $source_url, $user_data );
    wp_send_json_success();
}


// ═══════════════════════════════════════════════════════════════════════════
//  FUNÇÃO CENTRAL DE ENVIO CAPI
// ═══════════════════════════════════════════════════════════════════════════

function ps_capi_send( $event_name, $event_id, $custom_data = [], $source_url = '', $extra_user_data = [] ) {
    if ( empty( $source_url ) ) $source_url = ps_current_url();

    $user_data = array_merge(
        [
            'client_ip_address' => ps_get_ip(),
            'client_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
                                    ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] )
                                    : '',
        ],
        $extra_user_data
    );

    $payload = [
        'data' => [[
            'event_name'       => $event_name,
            'event_time'       => time(),
            'event_id'         => $event_id,
            'action_source'    => 'website',
            'event_source_url' => $source_url,
            'user_data'        => $user_data,
        ]],
    ];

    if ( ! empty( $custom_data ) ) {
        $payload['data'][0]['custom_data'] = $custom_data;
    }

    wp_remote_post(
        'https://graph.facebook.com/v20.0/' . PS_PIXEL_ID . '/events?access_token=' . PS_CAPI_TOKEN,
        [
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
            'timeout'  => 8,
            'blocking' => false,
        ]
    );
}


// ═══════════════════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════════════════

function ps_current_url() {
    $scheme = ( is_ssl() || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) )
              ? 'https' : 'http';
    return $scheme . '://'
        . sanitize_text_field( $_SERVER['HTTP_HOST'] )
        . sanitize_text_field( $_SERVER['REQUEST_URI'] );
}

function ps_get_ip() {
    foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $k ) {
        if ( ! empty( $_SERVER[$k] ) ) return trim( explode( ',', $_SERVER[$k] )[0] );
    }
    return '';
}
