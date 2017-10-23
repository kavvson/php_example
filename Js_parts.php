<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *
 */
class Js_parts extends CI_Model {
  public function __construct() {
        parent::__construct();
    }
    public  function edycja_wydatku() {
        $this->load->view("js_parts/select2/kategoria");
    }


    public function pracownik_do_reki()
    {
       // $this->load->view("js_parts/pracownik/do_reki",TRUE);
    }

}
