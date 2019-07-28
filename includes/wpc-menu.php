<?php
if (!class_exists('WPcleverMenu')) {
  class WPcleverMenu
  {
    function __construct()
    {
      add_action('admin_menu', array($this, 'admin_menu'));
    }

    function admin_menu()
    {
      add_menu_page(
        'WPclever',
        'Newgraph',
        'manage_options',
        'wpclever',
        array(&$this, 'welcome_content'),
        WPC_URI . 'assets/images/wpc-icon.png',
        26
      );
      add_submenu_page('wpclever', 'About', 'About', 'manage_options', 'wpclever');
    }

    function welcome_content()
    {
      ?>
      <div class="wpclever_welcome_page wrap">
        <h1>Newgraph Design <span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span> &darr;</h1>
        <div class="card">
          <h2 class="title">Welcome</h2>
          <p>
            Here you can configure packaged product settings!
          </p>
        </div>

        <div class="card">
          <h2 class="title">Contact</h2>
          <p>
            Feel free to contact us via <a href="mailto:newgraphil@gmail.com" target="_blank">email
            </a> :)<br />
            Website: <a href="https://newgraphdesign.com/apps/" target="_blank">https://newgraphdesign.com/apps/</a>
          </p>
        </div>
      </div>
    <?php
    }
  }

  new WPcleverMenu();
}
