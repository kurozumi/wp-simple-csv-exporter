<?php
/*
  Plugin Name: Wp Simple CSV Exporter
  Version: 0.3
  Description: simple csv exporter
  Author: kurozumi
  Author URI: http://a-zumi.net
  Plugin URI: http://a-zumi.net
  Text Domain: wp-simple-csv-exporter
  Domain Path: /languages
 */

$simple_csv_exporter = new Simple_CSV_Exporter;
$simple_csv_exporter->register();

class Simple_CSV_Exporter
{
	const NAME     = "シンプルCSVエクスポーター";	
	const FILEMANE = "export.csv";

	public function register()
	{
		$this->error = new WP_Error();

		add_action('plugins_loaded', array($this, 'plugins_loaded'));
	}

	public function plugins_loaded()
	{
		add_action('admin_menu',                      array($this, 'add_submemu_page'));
		add_action('init',                            array($this, 'download'));
		add_action('wp_ajax_print_taxonomies',        array($this, 'print_taxonomies'));
		add_action('wp_ajax_nopriv_print_taxonomies', array($this, 'print_taxonomies'));
		add_action('wp_ajax_print_meta_keys',         array($this, 'print_meta_keys'));
		add_action('wp_ajax_nopriv_print_meta_keys',  array($this, 'print_meta_keys'));
	}

	public function add_submemu_page()
	{
		add_submenu_page('tools.php', self::NAME, self::NAME, 'manage_options', 'simple-csv-exporter', array($this, 'print_options_page'));
	}

	public function print_options_page()
	{
		?>
		<script>
			(function ($) {
				$(document).on('change', '#post-type select', function () {
					$.post(ajaxurl, {
						action: 'print_meta_keys',
						post_type: $(this).val()
					}, function (response) {
						$('#meta-keys').empty().append(response);
					});
                    
					$.post(ajaxurl, {
						action: 'print_taxonomies',
						post_type: $(this).val()
					}, function (response) {
						$('#taxonomies').empty().append(response);
					});
				});
			})(window.jQuery);
		</script>
		<div class="wrap">
			<h2><?php echo esc_html(self::NAME);?></h2>
			<form action="" method="post">
				<?php wp_nonce_field('csv_exporter'); ?>
				<table class="form-table" id="post-type">
					<tr valign="top">
						<th scope="row"><label for="inputtext">投稿タイプを選択</label></th>
						<td>
							<select name="type"　class="regular-text">
								<option value="all"><?php _e('All'); ?></option>
								<option value="post"><?php _e('Posts'); ?></option>
								<option value="page"><?php _e('Pages'); ?></option>
								<?php foreach (get_post_types(array('_builtin' => false, 'can_export' => true), 'objects') as $post_type) : ?>
									<option value="<?php echo esc_attr($post_type->name); ?>"><?php echo esc_attr($post_type->label); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
                <table class="form-table" id="taxonomies"></table>
				<table class="form-table" id="meta-keys"></table>
				<p class="submit"><input type="submit" class="button-primary" value="エクスポート" /></p>
			</form>
		</div>
		<?php
	}

	/**
	 * エラーが発生したらメッセージを通知
	 */
	public function print_error_message()
	{
		if ($message = get_transient('simple-csv-exporter-errors'))
		{
			?>
			<div id="message" class="error notice is-dismissible">
				<p><?php echo esc_html($message); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">この通知を非表示にする</span>
				</button>
			</div>
			<?php
		}
	}
    
    /**
     * タクソノミーがあれば表示
     */
    public function print_taxonomies()
    {
		if ($taxonomy_objects = get_object_taxonomies($_POST['post_type'], 'objects' ))
		{
			?>
			<tr valign="top">
				<th scope="row"><label for="inputtext">タクソノミーを選択</label></th>
				<td>
					<?php foreach ($taxonomy_objects as $taxonomy): ?>
                    <p><label><input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($taxonomy->name); ?>" /><?php echo esc_html($taxonomy->name); ?></label></p> 
					<?php endforeach; ?>
				</td>
			</tr>
			<?php
		}

		die();
    }

	/**
	 * カスタムフィールドがあれば表示
	 */
	public function print_meta_keys()
	{
		if ($meta_keys = $this->get_meta_keys($_POST['post_type']))
		{
			?>
			<tr valign="top">
				<th scope="row"><label for="inputtext">カスタムフィールドを選択</label></th>
				<td>
					<?php foreach ($meta_keys as $meta_key): ?>
						<label><input type="checkbox" name="meta_keys[]" value="<?php echo esc_attr($meta_key); ?>" /><?php echo esc_html($meta_key); ?></label> 
					<?php endforeach; ?>
				</td>
			</tr>
			<?php
		}

		die();
	}

	public function download()
	{
		if (isset($_POST['type']))
		{
			try {
				
				check_admin_referer('csv_exporter');
				
				$this->do_csv_export();
				
			} catch (Exception $ex) {

				set_transient('simple-csv-exporter-errors', $ex->getMessage(), 10);

				add_action('admin_notices', array($this, 'print_error_message'));
			}
		}
	}

	public function get_meta_keys($post_type)
	{
		global $wpdb;

		$query = <<< __EOS__
			SELECT
				meta_key
			FROM $wpdb->postmeta
			JOIN $wpdb->posts
				ON $wpdb->posts.ID = $wpdb->postmeta.post_id
			WHERE 
				$wpdb->posts.post_type LIKE '%s' AND
				$wpdb->postmeta.meta_key NOT LIKE '%s'
			GROUP BY meta_key
			ORDER BY meta_key
__EOS__;
		$prepare = $wpdb->prepare($query, array($post_type, '\_%'));
		return $wpdb->get_col($prepare);
	}

	public function get_posts_from_type($post_type)
	{
		global $wpdb;

		$query = <<< __EOS__
			SELECT 
				ID,
				post_name,
				post_type,
				post_status,
				post_title,
				post_content
			FROM $wpdb->posts
			WHERE post_status LIKE 'publish' AND
			post_type LIKE '%s'
__EOS__;
		$prepare = $wpdb->prepare($query, array($post_type));
		return $wpdb->get_results($prepare, ARRAY_A);
	}

	/**
	 * カテゴリをセット
	 * @param type $post_id
	 * @param type $post
	 */
	public function set_terms($post_id, &$post)
	{
		if (isset($_POST['taxonomies']) && !empty($_POST['taxonomies']))
		{
			foreach ($_POST['taxonomies'] as $taxonomy)
			{
				$term_list = "";
				if ($terms = get_the_terms($post_id, $taxonomy))
				{
                  if($terms[0]) {
                    $term_list = array_map(function($term){
                      return $term->name;
                    }, $terms);
                    $term_list = implode(",", $term_list);
                  }
				}
 
                $post = array_merge($post, array($taxonomy => $term_list));
            }
        }
	}
	
	/**
	 * カスタムフィールドをセット
	 * @param type $post_id
	 * @param type $post
	 */
	public function set_post_meta($post_id, &$post)
	{
		if (isset($_POST['meta_keys']) && !empty($_POST['meta_keys']))
		{
			foreach ($_POST['meta_keys'] as $key)
			{
				$field = "";
				if ($fields = get_post_custom($post_id))
				{
                    if(is_array($fields[$key])) {
                      $field = implode(',', $fields[$key]);
                    }
				}

				$post = array_merge($post, array($key => $field));
			}
		}
	}
	
	/**
	 * 投稿情報カラム以外のカラムを追加
	 * @param type $posts
	 * @return type
	 */
	public function add_column(&$posts)
	{
		$self = $this;
		$posts = array_map(function($post) use($self) {

			// カテゴリがあれば追加
			$self->set_terms($post["ID"], $post);

			// カスタムフィールドを追加
			$self->set_post_meta($post['ID'], $post);

			return $post;
		}, $posts);		
	}

	/**
	 * エクスポート実行
	 * @global type $wpdb
	 * @throws Exception
	 */
	public function do_csv_export()
	{
		global $wpdb;

		$post_type = get_post_type_object($_POST['type']);

		if (!$post_type)
			throw new Exception("post_typeが正しくありません。");

		$posts = $this->get_posts_from_type($post_type->name);

		if (!$posts)
			throw new Exception(sprintf("%sの記事が見つかりませんでした。", $post_type->label));

		// 投稿情報カラム以外のカラムを追加
		$this->add_column($posts);
		
		// 項目名を取得
		$head[] = array_keys(current($posts));

		// 先頭に項目名を追加
		$list = array_merge($head, $posts);

		// 1時データを保存するためストリームを準備
		$fp = fopen('php://memory', 'r+b');

		// 配列をカンマ区切りにしてストリームに書き込み
		foreach ($list as $fields)
		{
			fputcsv($fp, $fields);
		}

		// ポインタを先頭に戻す
		rewind($fp);

		// CSVフォーマットされた文字列をストリームから読みだして変数に格納
		$tmp = str_replace(PHP_EOL, "\r\n", stream_get_contents($fp));

		fclose($fp);

		$tmp = mb_convert_encoding($tmp, 'SJIS-win', 'UTF-8');

		header('Content-Type:application/octet-stream');
		header('Content-Disposition:filename=' . self::FILEMANE);
		header('Content-Length:' . strlen($tmp));
		echo $tmp;  //ダウンロード
		exit;
	}

}
