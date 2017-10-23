<?PHP

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class File_handler extends CI_Model {

    private $base_p = "./files/";
    private $ext = 'pdf|csv|doc|txt';
    private $size = 5000000;
    

    function _push_file($path, $name) {
        // make sure it's a file before doing anything!
        if (is_file($path)) {
            // required for IE
            if (ini_get('zlib.output_compression')) {
                ini_set('zlib.output_compression', 'Off');
            }

            // get the file mime type using the file extension
            $this->load->helper('file');

            $mime = get_mime_by_extension($path);

            // Build the headers to push out the file properly.
            header('Pragma: public');     // required
            header('Expires: 0');         // no cache
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
            header('Cache-Control: private', false);
            header('Content-Type: ' . $mime);  // Add the mime type from Code igniter.
            header('Content-Disposition: attachment; filename="' . basename($name) . '"');  // Add the file name
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . filesize($path)); // provide file size
            header('Connection: close');
            readfile($path); // push it out
            exit();
        }
    }

    public function fsize($s) {
        $this->size = $s;
    }

    public function fext($ext) {
        $this->ext = $ext;
    }

    public function upload_file($field, $subpath) {
    
        if (!empty($_FILES[$field]['name'])) {
            $config['upload_path'] = $this->base_p . '' . $subpath;
            $config['allowed_types'] = $this->ext;
            $config['max_size'] = $this->size; // 5 mb
            $config['encrypt_name'] = TRUE;
            if (!is_dir($config['upload_path'])) {
                mkdir($config['upload_path'], 0777, TRUE);
            }
            $this->load->library('upload', $config);

            if (!$this->upload->do_upload($field)) {
                $status = 'error';
                $msg = $this->upload->display_errors('', '');
                return json_encode(array('result' => 'error', 'msg' => $msg));
                die();
            } else {
                $data = $this->upload->data();
            }
            return $path = 'files' . $subpath . '/' . $data['file_name'];
        }
    }

}
