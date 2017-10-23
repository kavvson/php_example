<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Przychody_dt_model
 *
 * @author Kavvson
 */
class Kontrahent_dt_model extends CI_Model {

    var $table = 'kontrahenci';
    var $column_order = array(
        'id_kontrahenta',
        "nazwa",
        "nip",
        "regon",
        "krs",
        "spec",
        "phone",
        "char_prawny",
    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable 
    var $order = array("id_kontrahenta", "asc"); // default order 

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    private function _get_datatables_query() {

        $this->db->select("kontrahenci.*");

        if ($this->input->post('s_kontrahent')) {
            $this->db->where('kontrahenci.id_kontrahenta', $this->input->post('s_kontrahent'));
        }


        $this->db->from($this->table);
        $i = 0;



        if (isset($_POST['order'])) { // here order processing
            $this->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } else if (isset($this->order)) {
            $order = $this->order;
            $this->db->order_by(key($order), $order[key($order)]);
        }
    }

    public function get_datatables() {
        $this->_get_datatables_query();
        $skip = FALSE;
        if (empty($this->input->post('length'))) {
            $l = 10;
            $s = 0;
        } else {
            $l = $this->input->post('length');
            $s = $this->input->post('start');
        }if ($this->input->post('length') == -1) {
            $skip = TRUE;
        }

        if (!$skip) {
            $this->db->limit($l, $s);
        }


        $query = $this->db->get();
        //echo $this->db->last_query();
        return $query->result();
    }

    public function count_filtered() {
        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );
        $this->_get_datatables_query();
        $query = $this->db->get();
        return array(
            'count' => $query->num_rows(),
            'respo' =>$reponse
        );
    }

    public function count_all() {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }

}
