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
class Kontrakt_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    /*
     * Get kontrakty by id_kontraktu
     */

    function get_kontrakty($id_kontraktu) {
        $this->db->select('kontrakty.nazwa as knazwa,kontrahenci.id_kontrahenta,kontrahenci.nazwa as kontrahent,kontrakty.zakonczony,kontrakty.id_kontraktu');
        $this->db->join('kontrahenci', 'kontrakty.kontrahent = kontrahenci.id_kontrahenta','LEFT');
        return $this->db->get_where('kontrakty', array('id_kontraktu' => $id_kontraktu))->row_array();
    }

    /*
     * Get all kontrakty count
     */

    function get_all_kontrakty_count() {
        $this->db->from('kontrakty');
        return $this->db->count_all_results();
    }

    /*
     * Get all kontrakty
     */

    function get_all_kontrakty($params = array()) {
        $this->db->select('kontrakty.nazwa as knazwa,kontrahenci.nazwa as kontrahent,kontrakty.zakonczony,kontrakty.id_kontraktu');
        $this->db->join('kontrahenci', 'kontrakty.kontrahent = kontrahenci.id_kontrahenta','LEFT');
        $this->db->order_by('id_kontraktu', 'desc');
        if (isset($params) && !empty($params)) {
            $this->db->limit($params['limit'], $params['offset']);
        }
        return $this->db->get('kontrakty')->result_array();
    }

    /*
     * function to add new kontrakty
     */

    function add_kontrakty($params) {
        $this->db->insert('kontrakty', $params);
        return $this->db->insert_id();
    }

    /*
     * function to update kontrakty
     */

    function update_kontrakty($id_kontraktu, $params) {
        $this->db->where('id_kontraktu', $id_kontraktu);
        return $this->db->update('kontrakty', $params);
    }

    public function get_kontrakt_by_id($id) {
        $this->db->select('id_kontraktu as id,nazwa as text')
                ->from('kontrakty')
                ->where('id_kontraktu', $id);

        $query = $this->db->get();

        $result = $query->result_array();
        return (!empty($result[0]['text'])) ? $result[0]['text'] : "";
    }

    public function populate() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $data = array();
        $getAd = $this->input->get('term');
        $limit = $this->input->get('page_limit');
        $this->db->select('id_kontraktu as id,nazwa as text')
                ->from('kontrakty')
                ->where('zakonczony', 0)
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
