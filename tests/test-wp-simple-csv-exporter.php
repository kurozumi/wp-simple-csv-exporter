<?php

class WpSimpleCsvExporterTest extends WP_UnitTestCase 
{
	function setup()
	{
		parent::setUp();
		
		$this->plugin = new Simple_CSV_Exporter();
	}
	
	function test_get_meta_keys()
	{
		$post_id = $this->factory->post->create();
		
		add_post_meta($post_id, "test_key1", "test1");
		add_post_meta($post_id, "test_key2", "test2");

		$result = $this->plugin->get_meta_keys('post');
		
		foreach($result as $key){
			$this->assertRegExp("/^[^_]/i", $key);
		}
		
		$this->assertContains('test_key1', $result);
		
		
		$post_id = $this->factory->post->create(array('post_type' => 'page'));
		
		add_post_meta($post_id, "test_key1", "test1");
		add_post_meta($post_id, "test_key2", "test2");

		$result = $this->plugin->get_meta_keys('page');
		
		foreach($result as $key){
			$this->assertRegExp("/^[^_]/i", $key);
		}
		
		$this->assertContains('test_key1', $result);
	}
	
	function test_get_posts_from_type()
	{
		$post_id = $this->factory->post->create_many(25);
		
		$this->assertCount(25, $this->plugin->get_posts_from_type('post'));
	}
	
	function test_get_categories()
	{
		$post_id = $this->factory->post->create_many(25);
		
		$results = $this->plugin->get_posts_from_type('post');
		
		$this->plugin->add_column($results);
		
		foreach($results as $result){
			$this->assertArrayHasKey('post_category', $result);
			$this->assertArrayHasKey('post_tags', $result);
		}

	}
}
