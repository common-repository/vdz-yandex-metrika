<?php
/*
Plugin Name: VDZ Yandex Metrika Plugin
Plugin URI:  http://online-services.org.ua
Description: Простое добавление счетчика Яндекс Метрики на свой сайт
Version:     1.7.7
Author:      VadimZ
Author URI:  http://online-services.org.ua#vdz-yandex-metrika
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'VDZ_YM_API', 'vdz_info_yandex_metrika' );

require_once 'api.php';
require_once 'updated_plugin_admin_notices.php';

// Код активации плагина
register_activation_hook( __FILE__, 'vdz_ym_activate_plugin' );
function vdz_ym_activate_plugin() {
	global $wp_version;
	if ( version_compare( $wp_version, '3.8', '<' ) ) {
		// Деактивируем плагин
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin required WordPress version 3.8 or higher' );
	}

	add_option( 'vdz_yandex_metrika_front_section', 'footer' );
	add_option( 'vdz_yandex_metrika_webvisor', 'v2' );
	add_option( 'vdz_yandex_metrika_informer', 0 );
	add_option( 'vdz_yandex_metrika_cdn', 0 );

	do_action( VDZ_YM_API, 'on', plugin_basename( __FILE__ ) );
}

// Код деактивации плагина
register_deactivation_hook( __FILE__, function () {
	$plugin_name = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
	$response = wp_remote_get( "http://api.online-services.org.ua/off/{$plugin_name}" );
	if ( ! is_wp_error( $response ) && isset( $response['body'] ) && ( json_decode( $response['body'] ) !== null ) ) {
		//TODO Вывод сообщения для пользователя
	}
} );
//Сообщение при отключении плагина
add_action( 'admin_init', function (){
	if(is_admin()){
		$plugin_data = get_plugin_data(__FILE__);
		$plugin_name = isset($plugin_data['Name']) ? $plugin_data['Name'] : ' us';
		$plugin_dir_name = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
		$handle = 'admin_'.$plugin_dir_name;
		wp_register_script( $handle, '', null, false, true );
		wp_enqueue_script( $handle );
		$msg = '';
		if ( function_exists( 'get_locale' ) && in_array( get_locale(), array( 'uk', 'ru_RU' ), true ) ) {
			$msg .= "Спасибо, что были с нами! ({$plugin_name}) Хорошего дня!";
		}else{
			$msg .= "Thanks for your time with us! ({$plugin_name}) Have a nice day!";
		}
		wp_add_inline_script( $handle, "document.getElementById('deactivate-".esc_attr($plugin_dir_name)."').onclick=function (e){alert('".esc_attr( $msg )."');}" );
	}
} );


/*Добавляем новые поля для в настройках шаблона шаблона для верификации сайта*/
function vdz_ym_theme_customizer( $wp_customize ) {

	if ( ! class_exists( 'WP_Customize_Control' ) ) {
		exit;
	}

	// Добавляем секцию для идетнтификатора YM
	$wp_customize->add_section(
		'vdz_yandex_metrika_section',
		array(
			'title'    => __( 'VDZ Yandex Metrika' ),
			'priority' => 10,
		// 'description' => __( 'Yandex Metrika code on your site' ),
		)
	);
	// Добавляем настройки
	$wp_customize->add_setting(
		'vdz_yandex_metrika_code',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_setting(
		'vdz_yandex_metrika_front_section',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_setting(
		'vdz_yandex_metrika_webvisor',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_setting(
		'vdz_yandex_metrika_informer',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_setting(
		'vdz_yandex_metrika_cdn',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	// Yandex Metrika
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_yandex_metrika_code',
			array(
				'label'       => __( 'Yandex Metrika' ),
				'section'     => 'vdz_yandex_metrika_section',
				'settings'    => 'vdz_yandex_metrika_code',
				'type'        => 'text',
				'description' => __( 'Сохраните свой код ID Yandex Metrika (состоящий только из цифр):' ),
				'input_attrs' => array(
					'placeholder' => 'XXXXXXXX', // для примера
				),
			)
		)
	);

	// Footer OR HEAD
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_yandex_metrika_front_section',
			array(
				'label'       => __( 'FrontEnd' ),
				'section'     => 'vdz_yandex_metrika_section',
				'settings'    => 'vdz_yandex_metrika_front_section',
				'type'        => 'select',
				'description' => __( 'Где вывести счетчик?' ),
				'choices'     => array(
					'head'   => 'Head',
					'footer' => 'Footer (Default)',
				),
			)
		)
	);

	// Vebvizor 2.0 (beta) or default
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_yandex_metrika_webvisor',
			array(
				'label'       => __( 'Вебвизор 2.0' ),
				'section'     => 'vdz_yandex_metrika_section',
				'settings'    => 'vdz_yandex_metrika_webvisor',
				'type'        => 'select',
				'description' => __( 'Новый Вебвизор 2.0' ),
				'choices'     => array(
					'old' => 'Нет',
					'v2'  => 'Да',
				),
			)
		)
	);

	// Show OR Hide Informer
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_yandex_metrika_cdn',
			array(
				'label'       => __( 'CDN' ),
				'section'     => 'vdz_yandex_metrika_section',
				'settings'    => 'vdz_yandex_metrika_cdn',
				'type'        => 'select',
				'description' => __( 'Alternative CDN' ),
				'choices'     => array(
					0 => __( 'Нет' ),
					1 => __( 'Да' ),
				),
			)
		)
	);

	// Show OR Hide Informer
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_yandex_metrika_informer',
			array(
				'label'       => __( 'Informer' ),
				'section'     => 'vdz_yandex_metrika_section',
				'settings'    => 'vdz_yandex_metrika_informer',
				'type'        => 'select',
				'description' => __( 'Show Informer or use shortcode' ) . ': <code>[vdz_ym_informer_shortcode]</code>',
				'choices'     => array(
					1 => __( 'Show' ),
					0 => __( 'Hide' ),
				),
			)
		)
	);

	// Добавляем ссылку на сайт
	$wp_customize->add_setting(
		'vdz_yandex_metrika_link',
		array(
			'type' => 'option',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_yandex_metrika_link',
			array(
				// 'label'    => __( 'Link' ),
							'section' => 'vdz_yandex_metrika_section',
				'settings'            => 'vdz_yandex_metrika_link',
				'type'                => 'hidden',
				'description'         => '<br/><a href="//online-services.org.ua#vdz-yandex-metrika" target="_blank">VadimZ</a>',
			)
		)
	);
}
add_action( 'customize_register', 'vdz_ym_theme_customizer', 1 );


// Добавляем допалнительную ссылку настроек на страницу всех плагинов
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function( $links ) {
		$settings_link = '<a href="' . get_admin_url() . 'customize.php?autofocus[section]=vdz_yandex_metrika_section">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

// Добавляем мета теги в head
if ( ! is_admin() ) {
	$vdz_yandex_metrika_front_section = get_option( 'vdz_yandex_metrika_front_section' ) ? get_option( 'vdz_yandex_metrika_front_section' ) : 'footer';
	if ( 'footer' === $vdz_yandex_metrika_front_section ) {
		add_action( 'wp_footer', 'vdz_ym_show_code', 1000 );
	} else {
		add_action( 'wp_head', 'vdz_ym_show_code', 1000 );
	}
}

function vdz_ym_show_code() {
	$code_str    = "\r\n" . '<!--Start VDZ Yandex Metrika Plugin-->' . "\r\n";
	$vdz_ym_code = get_option( 'vdz_yandex_metrika_code' );
	$vdz_ym_code = trim( $vdz_ym_code );
	$vdz_ym_code = sanitize_text_field( $vdz_ym_code );
	$vdz_ym_code = esc_js( $vdz_ym_code );
	if ( ! empty( $vdz_ym_code ) && is_numeric( $vdz_ym_code ) ) {
		$vdz_yandex_metrika_webvisor = get_option( 'vdz_yandex_metrika_webvisor' ) ? get_option( 'vdz_yandex_metrika_webvisor' ) : 'old';
		if ( 'old' === $vdz_yandex_metrika_webvisor ) {
			$code_str .= '<!-- Yandex.Metrika counter --> <script type="text/javascript" > (function (d, w, c) { (w[c] = w[c] || []).push(function() { try { w.yaCounter' . $vdz_ym_code . ' = new Ya.Metrika({ id:' . $vdz_ym_code . ', clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true, trackHash:true, ecommerce:"dataLayer" }); } catch(e) { } }); var n = d.getElementsByTagName("script")[0], s = d.createElement("script"), f = function () { n.parentNode.insertBefore(s, n); }; s.type = "text/javascript"; s.async = true; s.src = "https://mc.yandex.ru/metrika/watch.js"; if (w.opera == "[object Opera]") { d.addEventListener("DOMContentLoaded", f, false); } else { f(); } })(document, window, "yandex_metrika_callbacks"); </script> <noscript><div><img src="https://mc.yandex.ru/watch/' . $vdz_ym_code . '" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter -->';
		} else {
            if(get_option( 'vdz_yandex_metrika_cdn' ) === '1' ){
	            $code_str .= '<!-- Yandex.Metrika counter --><script type="text/javascript" >(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window, document, "script", "https://cdn.jsdelivr.net/npm/yandex-metrica-watch/tag.js", "ym");ym(' . $vdz_ym_code . ', "init", {clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true, trackHash:true, ecommerce:"dataLayer"});</script>
<noscript><div><img src="https://mc.yandex.ru/watch/' . $vdz_ym_code . '" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->';
            }else{
	            $code_str .= '<!-- Yandex.Metrika counter --><script type="text/javascript" >(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");ym(' . $vdz_ym_code . ', "init", {clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true, trackHash:true, ecommerce:"dataLayer"});</script>
<noscript><div><img src="https://mc.yandex.ru/watch/' . $vdz_ym_code . '" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->';
            }

			$code_str .= "<!--START ADD EVENTS FROM CF7--><script type='text/javascript'>document.addEventListener( 'wpcf7submit', function( event ) {
					  //event.detail.contactFormId;
					  if(ym){
				          //console.log(event.detail);
						  ym(" . $vdz_ym_code . ", 'reachGoal', 'VDZ_SEND_CONTACT_FORM_7');
						  ym(" . $vdz_ym_code . ", 'params', {
						      page_url: window.location.href, 
						      status: event.detail.status, 
						      locale: event.detail.contactFormLocale, 
						      form_id: event.detail.contactFormId, 
						  });
					  }
					}, false );
				</script><!--END ADD EVENTS FROM CF7-->";

			if ( in_array( 'vdz-call-back/vdz-call-back.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
				$code_str .= "<!--START ADD EVENTS FROM VDZ_CALL_BACK--><script type='text/javascript'>(function ($) {
					    $(document).on('ready', function () {
					        //console.log('YandexMetrika SEND VDZ_SEND_VDZ_CALL_BACK 0');
					        $(document).ajaxComplete(function( event, xhr, settings ) {
					            if (( settings.url === window.vdz_cb.ajax_url ) && /action=vdz_cb_send/.test(settings.data)) {
				                    if(window.ym){
					                	//console.log('YandexMetrika SEND VDZ_SEND_VDZ_CALL_BACK 2');
					                	ym(" . $vdz_ym_code . ", 'reachGoal', 'VDZ_SEND_VDZ_CALL_BACK');
					                }
					            }
					        });
					    });
					})(jQuery);</script><!--END ADD EVENTS FROM VDZ_CALL_BACK-->";
			}
		}
	}
	$code_str .= "\r\n" . '<!--End VDZ Yandex Metrika Plugin-->' . "\r\n";
	echo $code_str;
}

// Информер
$vdz_yandex_metrika_informer = (int) get_option( 'vdz_yandex_metrika_informer' );
if ( ! empty( $vdz_yandex_metrika_informer ) ) {
	add_action( 'wp_footer', 'vdz_ym_show_informer', 1100 );
}

function vdz_ym_show_informer() {
	$vdz_ym_code = get_option( 'vdz_yandex_metrika_code' );
	$vdz_ym_code = trim( $vdz_ym_code );
	$vdz_ym_code = sanitize_text_field( $vdz_ym_code );
	$vdz_ym_code = esc_attr( $vdz_ym_code );
	if ( ! empty( $vdz_ym_code ) && is_numeric( $vdz_ym_code ) ) {
		?>
		<!--Start VDZ Yandex Metrika Plugin Informer-->
		<!-- Yandex.Metrika informer -->
<a href="https://metrika.yandex.ru/stat/?id=<?php echo esc_attr( $vdz_ym_code ); ?>&amp;from=informer"
target="_blank" rel="nofollow"><img src="https://metrika-informer.com/informer/<?php echo esc_attr( $vdz_ym_code ); ?>/3_1_FFFFFFFF_EFEFEFFF_0_pageviews"
style="width:88px; height:31px; border:0;" alt="Яндекс.Метрика" title="Яндекс.Метрика: данные за сегодня (просмотры, визиты и уникальные посетители)" class="ym-advanced-informer" data-cid="<?php echo esc_attr( $vdz_ym_code ); ?>" data-lang="ru" /></a>
<!-- /Yandex.Metrika informer -->
<!--End VDZ Yandex Metrika Plugin Informer-->
		<?php
	}
}
function vdz_ym_informer_return() {
	$code_str    = "\r\n" . '<!--Start VDZ Yandex Metrika Plugin Informer-->' . "\r\n";
	$vdz_ym_code = get_option( 'vdz_yandex_metrika_code' );
	$vdz_ym_code = trim( $vdz_ym_code );
	$vdz_ym_code = sanitize_text_field( $vdz_ym_code );
	$vdz_ym_code = esc_js( $vdz_ym_code );
	if ( ! empty( $vdz_ym_code ) && is_numeric( $vdz_ym_code ) ) {
		$code_str .= '<!-- Yandex.Metrika informer -->
<a href="https://metrika.yandex.ru/stat/?id=' . $vdz_ym_code . '&amp;from=informer"
target="_blank" rel="nofollow"><img src="https://metrika-informer.com/informer/' . $vdz_ym_code . '/3_1_FFFFFFFF_EFEFEFFF_0_pageviews"
style="width:88px; height:31px; border:0;" alt="Яндекс.Метрика" title="Яндекс.Метрика: данные за сегодня (просмотры, визиты и уникальные посетители)" class="ym-advanced-informer" data-cid="' . $vdz_ym_code . '" data-lang="ru" /></a>
<!-- /Yandex.Metrika informer -->';
	}
	$code_str .= "\r\n" . '<!--End VDZ Yandex Metrika Plugin Informer-->' . "\r\n";
	return $code_str;
}
add_shortcode( 'vdz_ym_informer_shortcode', 'vdz_ym_informer_return' );
