<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

//die("Do not send");

class Email_Model extends CI_Model{

    function __construct()
    {
        parent::__construct();
        $this->load->config("Postageapp");
        $this->load->library("Postageapp");
    }

    public function nowa_premia($tablica = array()){
        $this->postageapp->from('Komer - Premie <powiadomenia@testhekko.hekko24.pl>');
        $this->postageapp->to(array("kavvson.a@gmail.com","d.kubicka@tdfap.pl","l.gajda@tdfap.pl","a.peciak@tdfap.pl","a.gajda@tdfap.pl"));
        // $this->postageapp->to(array("kavvson.a@gmail.com"));
        $this->postageapp->subject('Podanie o premię');

        $this->postageapp->template('notatka_copy');
        $this->postageapp->variables($tablica);
        $this->postageapp->send();
    }

}