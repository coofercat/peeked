<?php

/**
 * Editor plugin for Pico
 *
 * @author Gilbert Pellegrom
 * @link http://pico.dev7studios.com
 * @license http://opensource.org/licenses/MIT
 * @version 1.1
 */
class Peeked {

	private $is_admin;
	private $is_logout;
	private $plugin_path;
	private $password;

	public function __construct()
	{
		$this->is_admin = false;
		$this->is_logout = false;
		$this->plugin_path = dirname(__FILE__);
		$this->password = '';

		if(file_exists($this->plugin_path .'/peeked_config.php')){
			global $peeked_password;
			include_once($this->plugin_path .'/peeked_config.php');
			$this->password = $peeked_password;
		}
	}

	public function request_url(&$url)
	{
    // If the request is anything to do with Peeked, then
    // we start the PHP session
    if(substr($url, 0, 6) == 'peeked') {
      if(function_exists('session_status')) {
        if (session_status() == PHP_SESSION_NONE) {
          session_start();
        }
      } else {
        session_start();
      }
    }
		// Are we looking for /peeked?
		if($url == 'peeked') $this->is_admin = true;
		if($url == 'peeked/new') $this->do_new();
		if($url == 'peeked/open') $this->do_open();
		if($url == 'peeked/save') $this->do_save();
		if($url == 'peeked/delete') $this->do_delete();
		if($url == 'peeked/logout') $this->is_logout = true;
		if($url == 'peeked/files') $this->do_filemgr();
    if($url == 'peeked/commit') $this->do_commit();
    if($url == 'peeked/git') $this->do_git();
    if($url == 'peeked/pushpull') $this->do_pushpull();
	}

	public function before_render(&$twig_vars, &$twig)
	{
		if($this->is_logout){
			session_destroy();
			header('Location: '. $twig_vars['base_url'] .'/peeked');
			exit;
		}

		if($this->is_admin){
			header($_SERVER['SERVER_PROTOCOL'].' 200 OK'); // Override 404 header
			$loader = new Twig_Loader_Filesystem($this->plugin_path);
			$twig_editor = new Twig_Environment($loader, $twig_vars);
			if(!$this->password){
				$twig_vars['login_error'] = 'No password set for the Pico Editor.';
				echo $twig_editor->render('login.html', $twig_vars); // Render login.html
				exit;
			}

			if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']){
				if(isset($_POST['password'])){
					if(sha1($_POST['password']) == $this->password){
						$_SESSION['peeked_logged_in'] = true;
						$_SESSION['peeked_config'] = $twig_vars['config'];
					} else {
						$twig_vars['login_error'] = 'Invalid password.';
						echo $twig_editor->render('login.html', $twig_vars); // Render login.html
						exit;
					}
				} else {
					echo $twig_editor->render('login.html', $twig_vars); // Render login.html
					exit;
				}
			}

			echo $twig_editor->render('editor.html', $twig_vars); // Render editor.html
			exit; // Don't continue to render template
		}
	}

	/**
	 * Returns real file name to be edited.
	 *
	 * @param string $file_url the file URL to be edited
	 * @return string
	 */
	private static function get_real_filename($file_url)
    {

		$file_components = parse_url($file_url); // inner
		$base_components = parse_url($_SESSION['peeked_config']['base_url']);
		$file_path = rtrim($file_components['path'], '/');
		$base_path = rtrim($base_components['path'], '/');

		if(empty($file_path) || $file_path === $base_path)
        {
            return 'index';
		}
        else
        {
            $file_path = strip_tags(substr($file_path, strlen($base_path)));
            if(is_dir(CONTENT_DIR . $file_path))
                $file_path .= "/index";

            return $file_path;
        }
	}


    private function do_new()
    {
        if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
        $title = isset($_POST['title']) && $_POST['title'] ? strip_tags($_POST['title']) : '';
        $dir = isset($_POST['dir']) && $_POST['dir'] ? strip_tags($_POST['dir']) : '';
	if(substr($dir,0,1) != '/') {
	  $dir = "/$dir";
	}
	$dir = preg_replace('/\/+/', '/', $dir);

        $contentDir = CONTENT_DIR . $dir;
        if($contentDir[strlen(count($contentDir)-1)] != '/') {
	  $contentDir .= '/';
	}

        if(!is_dir($contentDir)) {
            if (!mkdir($contentDir, 0777, true)) {
                die(json_encode(array('error' => 'Can\'t create directory...')));
            }
        }

        $file = $this->slugify(basename($title));
        if(!$file) die(json_encode(array('error' => 'Error: Invalid file name')));

	// From the bottom of the $contentDir, look for format templates,
	// working upwards until we get to CONTENT_DIR
	$template = null;
	$workDir = $contentDir;
	while(strlen($workDir) >= strlen(CONTENT_DIR)) {
	  // See if there's a format template here...?
	  if(file_exists($workDir . 'format.templ')) {
	    $template = strip_tags(substr($workDir . 'format.templ', strlen(CONTENT_DIR)));
	    break;
	  }
	  // Now strip off the last bit of path from the $workDir
	  $workDir = preg_replace('/[^\/]*\/$/', '', $workDir);
	}

        $error = '';
        $file .= CONTENT_EXT;

	$content = null;
	if(!is_null($template)) {
	  $loader = new Twig_Loader_Filesystem(CONTENT_DIR);
          $twig = new Twig_Environment($loader, array('cache' => null));
          $twig->addExtension(new Twig_Extension_Debug());
          $twig_vars = array(
            'title' => $title,
            'date' => date('j F Y'),
            'time' => date('h:m:s'),
	    'author' => 'ralph',
          );
          $content = $twig->render($template, $twig_vars);
	}

	if(is_null($content)) {
          $content = '/*
Title: '. $title .'
Author:
Date: '. date('j F Y') .'
*/
';
	}
        if(file_exists($contentDir . $file))
        {
            $error = 'Error: A post already exists with this title';
        }
        else
        {
            if(strlen($content) !== file_put_contents($contentDir . $file, $content))
                $error = 'Error: can not create the post ... ';
        }

        $file_url = $dir .'/'. str_replace(CONTENT_EXT, '', $file);
	$file_url = preg_replace('/\/+/', '/', $file_url);

        die(json_encode(array(
            'title' => $title,
            'content' => $content,
            'file' => $file_url,
            'error' => $error
        )));
    }

    private function do_open()
    {
        if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        $file = self::get_real_filename($file_url);
        if(!$file) die('Error: Invalid file');

        $file .= CONTENT_EXT;
        if(file_exists(CONTENT_DIR . $file)) die(file_get_contents(CONTENT_DIR . $file));
        else die('Error: Invalid file');
    }

    private function do_save()
    {
        if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        $file = self::get_real_filename($file_url);
        if(!$file) die('Error: Invalid file');
        $content = isset($_POST['content']) && $_POST['content'] ? $_POST['content'] : '';
        if(!$content) die('Error: Invalid content');

        $file .= CONTENT_EXT;
        $error = '';
        if(strlen($content) !== file_put_contents(CONTENT_DIR . $file, $content))
            $error = 'Error: can not save changes ... ';

        die(json_encode(array(
            'content' => $content,
            'file' => $file_url,
            'error' => $error
        )));
    }

    private function do_delete()
    {
        if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
        $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
        $file = self::get_real_filename($file_url);
        if(!$file) die('Error: Invalid file');

        $file .= CONTENT_EXT;
        if(file_exists(CONTENT_DIR . $file)) die(unlink(CONTENT_DIR . $file));
    }

    private function do_filemgr()
    {
      if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
      $dir = isset($_POST['dir']) && $_POST['dir'] ? strip_tags($_POST['dir']) : '';
      if(substr($dir,0,1) != '/') {
        $dir = "/$dir";
      }
      $dir = preg_replace('/\/+/', '/', $dir);

      $contentDir = CONTENT_DIR . $dir;
      if($contentDir[strlen(count($contentDir)-1)] != '/') {
        $contentDir .= '/';
      }
      error_log("do_filemgr() dir=$dir contentDir=$contentDir");

      // Now stat files/directories inside here, and return an array
      // of file information. This duplicates what Pico does in its
      // page building

      die(json_encode(array(
        array('entry' => '/subdir', 'type' => 'dir', 'editable' => 0),
        array('entry' => '/one.md', 'type' => 'file', 'editable' => 1),
        array('entry' => '/two.md', 'type' => 'file', 'editable' => 1),
        array('entry' => '/four.md', 'type' => 'file', 'editable' => 1),
        array('entry' => '/five.png', 'type' => 'file', 'editable' => 0),
      )));
    }

    private function do_commit()
    {
      if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
      if($_SERVER['REQUEST_METHOD'] == 'POST') {
        return $this->do_commit_post();
      }
      return $this->do_commit_get();
    }

    private function do_commit_get()
    {
      if(file_exists('./plugins/peeked/commitform.html')) {
        # Do the git stuff...
        require_once 'Git-php-lib';
        $repo = Git::open('.');
        $status = '';
        try {
          $status = $repo->porcelain();
        }
        catch(Exception $e) {
          $status = array('Failed to run git-status: ' . $e->getMessage());
        }

        $loader = new Twig_Loader_Filesystem('./plugins/peeked');
        $twig = new Twig_Environment($loader, array('cache' => null));
        $twig->addExtension(new Twig_Extension_Debug());
        $twig_vars = array(
          'status' => $status,
        );
        $content = $twig->render('commitform.html', $twig_vars);
        die($content);
      } else {
        die('Sorry, commitform.html was not found in the Peeked plugin. This is an installation problem.');
      }
    }

    private function do_commit_post()
    {
      // $_REQUEST['file'] is an array of file names. We don't trust our client,
      // so will re-run 'porcelain' to get a list of files. We'll only 'git add'
      // any files supplied by the user that are in the list we get from porcelain
      // we'll then go ahead and commit them with the message supplied
      require_once 'Git-php-lib';
      $repo = Git::open('.');
      $status = $repo->porcelain();
      $git_files = array();
      foreach($status as $item) {
        $git_files[$item['file']] = $item['y'];
      }

      $to_add = array();
      $to_rm = array();
      foreach($_REQUEST['file'] as $requested_file) {
        if(array_key_exists($requested_file, $git_files)) {
          if($git_files[$requested_file] == 'D') {
            $to_rm[] = $requested_file;
          } else {
            $to_add[] = $requested_file;
          }
        }
      }
  
      $add_output = '';
      if(count($to_add) > 0) {
        try {
          $add_output = $repo->add($to_add);
        }
        catch(Exception $e) {
          $add_output = 'Failed to run git-add: ' . $e->getMessage();
        }
      }
      #$add_output = preg_replace('/\r?\n\r?/', "<br>\n", $add_output);
      if(count($to_rm) > 0) {
        $rm_output = '';
        try {
          $rm_output = $repo->rm($to_rm);
        }
        catch(Exception $e) {
          $rm_output = 'Failed to run git-rm: ' . $e->getMessage();
        }
      }

      $commit_output = '';
      try {
        $commit_output = $repo->commit($_REQUEST['message'], false);
      }
      catch(Exception $e) {
        $commit_output = 'Failed to run git-commit: ' . $e->getMessage();
      }
      #$commit_output = preg_replace('/\r?\n\r?/', "<br>\n", $add_output);

      if(file_exists('./plugins/peeked/commitresponse.html')) {
        $loader = new Twig_Loader_Filesystem('./plugins/peeked');
        $twig = new Twig_Environment($loader, array('cache' => null));
        $twig->addExtension(new Twig_Extension_Debug());
        $twig_vars = array(
          'add' => $add_output,
          'rm' => $rm_output,
          'commit' => $commit_output,
        );
        $content = $twig->render('commitresponse.html', $twig_vars);
        die($content);
      } else {
        die('Sorry, commitresponse.html was not found in the Peeked plugin. This is an installation problem.');
      }
    }

    private function do_pushpull()
    {
      if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));
      if($_SERVER['REQUEST_METHOD'] == 'POST') {
        return $this->do_pushpull_post();
      }
      return $this->do_pushpull_get();
    }

    private function do_pushpull_get()
    {
      if(file_exists('./plugins/peeked/pushpullform.html')) {
        # Do the git stuff...
        require_once 'Git-php-lib';
        $repo = Git::open('.');
        $remotes = '';
        try {
          $remotes_string = $repo->run('remote');
          $remotes = preg_split('/\s*\r?\n\r?\s*/', $remotes_string, 0, PREG_SPLIT_NO_EMPTY);
        }
        catch(Exception $e) {
          $remotes = array('Failed to get git sources: ' . $e->getMessage());
        }

        $loader = new Twig_Loader_Filesystem('./plugins/peeked');
        $twig = new Twig_Environment($loader, array('cache' => null));
        $twig->addExtension(new Twig_Extension_Debug());
        $twig_vars = array(
          'remotes' => $remotes,
        );
        $content = $twig->render('pushpullform.html', $twig_vars);
        die($content);
      } else {
        die('Sorry, pushpullform.html was not found in the Peeked plugin. This is an installation problem.');
      }
    }

    private function do_pushpull_post()
    {
      if(file_exists('./plugins/peeked/pushpullresponse.html')) {
        # Do the git stuff...
        require_once 'Git-php-lib';
        $repo = Git::open('.');
        $remotes = array();
        try {
          $remotes_string = $repo->run('remote');
          $remotes = preg_split('/\s*\r?\n\r?\s*/', $remotes_string, 0, PREG_SPLIT_NO_EMPTY);
        }
        catch(Exception $e) {
          $status = array('Failed to get git sources: ' . $e->getMessage());
        }

        $output = 'xyz';

        # Now make the the selected repo is one in the remotes list
        if(in_array($_REQUEST['remote'], $remotes)) {
          # Selected repo is acceptable, so go Git push/pull

          try {
            if($_REQUEST['operation'] == 'push') {
              $output = $repo->push($_REQUEST['remote'], 'master');
              error_log("output = $output");
            } elseif($_REQUEST['operation'] == 'pull') {
              $output = $repo->pull($_REQUEST['remote'], 'master');
            } else {
              $output = 'Sorry, that operation is not allowed';
            }
          }
          catch(Exception $e) {
            $output = $e->getMessage();
          }
        } else {
          # Not an acceptable remote
          $output = 'Sorry, that remote is not allowed';
        }

        # And do output...
        $loader = new Twig_Loader_Filesystem('./plugins/peeked');
        $twig = new Twig_Environment($loader, array('cache' => null));
        $twig->addExtension(new Twig_Extension_Debug());
        $twig_vars = array(
          'output' => $output,
        );
        $content = $twig->render('pushpullresponse.html', $twig_vars);
        die($content);
      } else {
        die('Sorry, pushpullresponse.html was not found in the Peeked plugin. This is an installation problem.');
      }
    }

    private function do_git() {
      if(!isset($_SESSION['peeked_logged_in']) || !$_SESSION['peeked_logged_in']) die(json_encode(array('error' => 'Error: Unathorized')));

      $output = array(
        'have_git' => 0,
        'have_repo' => 0,
        'remotes' => array(),
      );

      require_once 'Git-php-lib';
      $output['have_git'] = GitRepo::test_git();

      if($output['have_git']) {
        try {
          $repo = Git::open('.');
          if(Git::is_repo($repo)) {
            $output['have_repo'] = true;

            $remotes_string = $repo->run('remote');
            $output['remotes'] = preg_split('/\s*\r?\n\r?\s*/', $remotes_string, 0, PREG_SPLIT_NO_EMPTY);
          }
        }
        catch(Exception $e) { }
      }

      die(json_encode($output));
    }

    private function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // trim
        $text = trim($text, '-');
        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // lowercase
        $text = strtolower($text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text))
            {
                return 'n-a';
            }
        return $text;
    }
}

// This is for Vim users - please don't delete it
// vim: set filetype=php expandtab tabstop=2 shiftwidth=2:
