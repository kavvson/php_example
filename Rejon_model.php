<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Rejon_model
 *
 * @author Kavvson
 */
class Rejon_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    /*
     * Get rejony by id_rejonu
     */

    function get_rejony($id_rejonu) {
        return $this->db->get_where('rejony', array('id_rejonu' => $id_rejonu))->row_array();
    }

    /*
     * Get all rejony
     */

    function get_all_rejony() {
        $this->db->order_by('id_rejonu', 'desc');
        return $this->db->get('rejony')->result_array();
    }

    /*
     * function to add new rejony
     */

    function add_rejony($params) {
        $this->db->insert('rejony', $params);
        return $this->db->insert_id();
    }

    /*
     * function to update rejony
     */

    function update_rejony($id_rejonu, $params) {
        $this->db->where('id_rejonu', $id_rejonu);
        return $this->db->update('rejony', $params);
    }


    public function populate() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $data = array();
        $getAd = $this->input->get('term');
        $limit = $this->input->get('page_limit');
        $this->db->select('id_rejonu as id,nazwa as text')
                ->from('rejony')
                ->like('nazwa', $getAd);

        $query = $this->db->limit($limit);
        $query = $this->db->get();

        $rowcount = $query->num_rows();
        //echo $this->db->last_query();
        // Make sure we have a result
        $result = $query->result_array();
        if (count($result) > 0) {
            foreach ($result as $key => $value) {
                $data[] = array('id' => $value['id'], 'text' => $value['text']);
            }
        } else {
            //$data[] = array('id' => '0', 'text' => 'No Products Found');
        }

        // return the result in json
        echo json_encode($data);
    }

}
