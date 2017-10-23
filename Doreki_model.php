<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Doreki_model extends CI_Model
{
    var $table = 'pracownik_doreki';
    var $column_order = array(
        null,
        'zarejestrowano',
        'kwota',
        'opis'
    );
    var $column_search = array(); //set column field database for datatable searchable 
    var $order = array('zarejestrowano' => 'asc'); // default order

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function custom_decimal($decimal)
    {
        $decimal = str_replace("Zł ", "", $decimal);
        $decimal = str_replace("dm3 ", "", $decimal);
        $decimal = str_replace(",", "", $decimal);


        if (preg_match('/^[0-9]+\.[0-9]{2}$/', $decimal)) {
            return $decimal;
        } else {
            return FALSE;
        }
    }

    protected function doRekiExists($d, $k, $p)
    {

        try {
            $this->db->trans_begin();
            $this->db->where('pracownik_doreki.zarejestrowano', $d);
            $this->db->where('pracownik_doreki.kwota', $k);
            $this->db->where('pracownik_doreki.fk_pracownik', $p);
            $this->db->from("pracownik_doreki");
            $query = $this->db->get();

            $wartosci = $query->result_array();


            $this->db->trans_commit();
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }

        return (count($wartosci) >= 1) ? TRUE : FALSE;
    }

    public function DodajDoReki($id)
    {
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }

        $opis = $this->input->post("cf_doreki_opis");
        $data = $this->input->post("cf_doreki_data");
        $pracownik = $this->input->post("fk_prac");

        $kwota = $this->custom_decimal($this->input->post('cf_doreki_kwota'));

        if (!$kwota) {
            $message = "Wartość brutto nie jest liczbą";
        }

        if (strlen($opis) > 250 || empty($opis)) {
            $message = "Opis musi się składać od 1 znaku do 250 znaków";
        }


        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $data)) {
            $message = "Nieprawidłowy format daty rrrr-mm-dd";
        }

        $status = 0;
        $message = "";

        if (empty($pracownik)) {
            $message = "Błąd1";
        }
        if ($this->doRekiExists($data, $kwota, $pracownik)) {
            $message = "Podana płatność jest już zarejestrowana";
        }

        try {
            $this->db->trans_begin();
            if (empty($message)) {
                $post_data = array(
                    'zarejestrowano	' => $data,
                    'kwota' => $kwota,
                    'opis' => $opis,
                    'fk_pracownik' => $pracownik,

                );

                $this->db->insert('pracownik_doreki', $post_data);
                $status = 1;
                $id = $this->db->insert_id();
            }else{
              //  $message="Błąd";
            }


            if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                if (is_numeric($id)) {
                    if ($status) {
                        $message = "Dodano";
                    }
                }
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
        }


        $responce = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $responce, "response" => array("status" => $status, "message" => $message))));
    }

    function dateDiff($start, $end)
    {

        $start_ts = strtotime($start);

        $end_ts = strtotime($end);

        $diff = $end_ts - $start_ts;

        return round($diff / 86400);

    }

    function rangeMonth($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    function rangeWeek($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
        $res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
        return $res;
    }

    private function _get_datatables_query()
    {

        $this->db->select("*");

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('zarejestrowano >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('zarejestrowano <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }
        $this->db->from($this->table);


        if (isset($_POST['order'])) { // here order processing
            $this->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } else if (isset($this->order)) {
            $order = $this->order;
            $this->db->order_by(key($order), $order[key($order)]);
        }
    }

    public function get_datatables()
    {
        $this->_get_datatables_query();
        $skip = FALSE;
        if (empty($this->input->post('length'))) {
            $l = 10;
            $s = 0;
        } else {
            $l = $this->input->post('length');
            $s = $this->input->post('start');
        }
        if ($this->input->post('length') == -1) {
            $skip = TRUE;
        }

        if (!$skip) {
            $this->db->limit($l, $s);
        }


        $query = $this->db->get();
        //echo $this->db->last_query();
        return $query->result();
    }

    public function agregacja($get)
    {

        $lacznie_brutto = 0;

        if (!empty($get)) {
            foreach ($get as $a) {
                $lacznie_brutto = bcadd($lacznie_brutto, $a['kwota'], 2);
            }
        }

        return
            array(
                'brutto' => $lacznie_brutto,
            );
    }

    public function count_filtered()
    {
        $this->_get_datatables_query();
        $query = $this->db->get();
        return array(
            'count' => $query->num_rows(),
            'agregacja' => $this->agregacja($query->result_array())
        );
    }

    public function count_all()
    {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }


}
