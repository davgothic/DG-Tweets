<?php
/*
Plugin Name: DG-Tweets
Plugin URI: http://davgothic.com/dg-tweets-wordpress-plugin/
Description: A widget that displays your Twitter Tweets
Author: David Hancock
Version: v0.1
Author URI: http://davgothic.com
*/

class DG_Tweets extends WP_Widget {
	
	private $dg_defaults;
	private $tweets_cache;

	/**
	 * Let's get things rolling!
	 */
	public static function dg_load()
	{
		add_action('widgets_init', array(__CLASS__, 'dg_widgets_init'));
	}

	/**
	 * Init the widget
	 */
	public static function dg_widgets_init() {
		register_widget('DG_Tweets');
	}

	/**
	 * Construct a new instance of the widget
	 */
	public function __construct()
	{
		$widget_options = array(
			'classname'    => 'DG_Tweets',
			'description'  => __('Displays your latest tweets.')
		);

		parent::__construct('DG_Tweets', __('DG-Tweets'), $widget_options);

		$this->tweets_cache = dirname(__FILE__).DIRECTORY_SEPARATOR.'dg-tweets.cache';

		// Set up the widget defaults
		$this->dg_defaults = array(
			'title'        => __('Latest Tweets'),
			'username'     => 'davgothic',
			'tweetcount'   => '5',
			'cachelength'  => '300',
			'followtext'   => __('Follow me on twitter'),
		);
	}

	/**
	 * Display the widget
	 */
	public function widget($args, $instance)
	{
		extract($args, EXTR_SKIP);

		$title        = apply_filters('widget_title', empty($instance['title']) ? $this->dg_defaults['title'] : $instance['title']);
		$username     = $instance['username'];
		$tweetcount   = $instance['tweetcount'];
		$cachelength  = $instance['cachelength'];
		$followtext   = $instance['followtext'];
		$feed         = $this->dg_get_tweets($username, $tweetcount, $cachelength);

		if ($feed === FALSE)
			return;

		echo $before_widget;

		if ($title)
		{
			echo $before_title, $title, $after_title;
		}

		?>

			<ul>
				<?php echo $feed ?>
			</ul>
			<?php if ($followtext): ?>
				<a href="http://twitter.com/<?php echo $username ?>" class="dg-tweets-follow-link"><?php echo $followtext ?></a>
			<?php endif ?>

		<?php

		echo $after_widget;
	}

	/**
	 * Handle widget settings update
	 */
	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;

		$instance['title']        = strip_tags($new_instance['title']);
		$instance['username']     = strip_tags(str_replace('@', '', $new_instance['username']));
		$instance['tweetcount']   = (int) $new_instance['tweetcount'];
		$instance['cachelength']  = (int) $new_instance['cachelength'];
		$instance['followtext']   = strip_tags($new_instance['followtext']);

		if ( ! $tweetcount = $instance['tweetcount'])
		{
 			$instance['tweetcount'] = $this->dg_defaults['tweetcount'];
		}
 		else if ($tweetcount < 1)
		{
 			$instance['tweetcount'] = 1;
		}
		else if ($tweetcount > 20)
		{
 			$instance['tweetcount'] = 20;
		}

		// Invalidate the cache
		if (file_exists($this->tweets_cache))
		{
			unlink($this->tweets_cache);
		}

		return $instance;
	}

	/**
	 * The widget settings form
	 */
	public function form($instance)
	{
		$instance = wp_parse_args( (array) $instance, $this->dg_defaults);

		?>
		<p>
			<label for="<?php echo $this->get_field_id('title') ?>"><?php _e('Title:') ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title') ?>" name="<?php echo $this->get_field_name('title') ?>" type="text" value="<?php echo $instance['title'] ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('username') ?>"><?php _e('Twitter Username:') ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('username') ?>" name="<?php echo $this->get_field_name('username') ?>" type="text" value="<?php echo $instance['username'] ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('tweetcount') ?>"><?php _e('Number of tweets (max 20):') ?></label>
			<input id="<?php echo $this->get_field_id('tweetcount') ?>" name="<?php echo $this->get_field_name('tweetcount') ?>" type="text" value="<?php echo $instance['tweetcount'] ?>" size="3" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('cachelength') ?>"><?php _e('Length of time to cache tweets (seconds):') ?></label>
			<input id="<?php echo $this->get_field_id('cachelength') ?>" name="<?php echo $this->get_field_name('cachelength') ?>" type="text" value="<?php echo $instance['cachelength'] ?>" size="5" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('followtext') ?>"><?php _e('Follow Link Text:') ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('followtext') ?>" name="<?php echo $this->get_field_name('followtext') ?>" type="text" value="<?php echo $instance['followtext'] ?>" />
		</p>
		<?php
	}

	/**
	 * Fetch the tweets from Twitter or cache
	 */
	private function dg_get_tweets($username, $tweetcount, $cachelength)
	{
		$twitter_api = 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name='.$username.'&include_entities=true&include_rts=true&count='.$tweetcount;

		// If the cache file has expired, fetch tweets
		if ( ! file_exists($this->tweets_cache) OR filemtime($this->tweets_cache) < (time() - $cachelength))
		{
			$curl = curl_init($twitter_api);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			$result = curl_exec($curl);
			curl_close ($curl);

			if ( ! $result)
			{
				// There was a problem, abort!
				return FALSE;
			}
			else
			{
				// Cache the results
				file_put_contents($this->tweets_cache, $result);
			}
		}
		else
		{
			// Read the tweets from cache
			$result = file_get_contents($this->tweets_cache);
		}

		$tweets = json_decode($result);

		$html = '';
		foreach ($tweets as $tweet)
		{
			// Parse links, mentions and hashtags
			$tweet_text = preg_replace('/((https?|s?ftp|ssh)\:\/\/[^"\s\<\>]*[^.,;\'">\:\s\<\>\)\]\!])/', '<a href="$1">$1</a>', $tweet->text);
			$tweet_text = preg_replace('/\B@([_a-z0-9]+)/i', '<a href="http://twitter.com/$1">@$1</a>', $tweet_text);
			$tweet_text = preg_replace('/(^|\s)#(\w*)/i', ' <a href="http://twitter.com/search?q=%23$2">#$2</a>', $tweet_text);

			$html .= '<li>';
			$html .= '<span>'.$tweet_text.'</span> ';
			$html .= '<a href="http://twitter.com/'.$username.'/statuses/'.$tweet->id_str.'" class="dg-tweets-created-at">'.$this->dg_relative_time($tweet->created_at).'</a>';
			$html .= '</li>';
		}

		return $html;
	}

	/**
	 * Display a user friendly string for the tweet post time
	 */
	private function dg_relative_time($time)
	{
		$values      = explode(' ', $time);
		$time        = $values[1].' '.$values[2].', '.$values[5].' '.$values[3];
		$parsed_date = strtotime($time);
		$relative_to = time();
		$delta       = (int) ($relative_to - $parsed_date);

		if ($delta < 60)
		{
			return 'less than a minute ago';
		}
		else if ($delta < 120)
		{
			return 'about a minute ago';
		}
		else if ($delta < (60 * 60))
		{
			return (int) ($delta / 60).' minutes ago';
		}
		else if ($delta < (120 * 60))
		{
			return 'about an hour ago';
		}
		else if ($delta < (24 * 60 * 60))
		{
			return 'about '. (int) ($delta / 3600).' hours ago';
		}
		else if ($delta < (48 * 60 * 60))
		{
			return '1 day ago';
		}
		else
		{
			return (int) ($delta / 86400).' days ago';
		}
	}

}

DG_Tweets::dg_load();
