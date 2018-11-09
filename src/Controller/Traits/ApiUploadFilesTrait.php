<?php
namespace EvilCorp\AwsCognito\Controller\Traits;

use EvilCorp\AwsApiGateway\Error\UnprocessableEntityException;
use Cake\Filesystem\File;
use Mimey\MimeTypes;
use Cake\Utility\Hash;

trait ApiUploadFilesTrait
{
    /*

    Usage:

    $data = [
        'avatar_file_name' => $this->_getFileFromRequest()
    ];

    $api_user = $this->ApiUsers->patchEntity($api_user, $data, [
        'accessibleFields' => [
            'avatar_file_name' => true,
        ]
    ]);

    */

    protected function _getFileFromRequest()
    {
        $file_raw       = $this->request->input();
        $content_length = (int) Hash::get($this->request->getHeader('Content-Length'), '0', 0);

        return $this->_RawFileToPHPFormat($file_raw, $content_length);
    }


    protected function _checkFileErrors($file_raw, $content_length)
    {
        $file_size = mb_strlen($file_raw, '8bit');

        $error = ($file_size !== $content_length)
            ? UPLOAD_ERR_PARTIAL
            : UPLOAD_ERR_OK;

        $error = ($file_size === 0)
            ? UPLOAD_ERR_NO_FILE
            : $error;

        $max_size_mb = min(
            ini_get('upload_max_filesize'),
            ini_get('post_max_size'),
            ini_get('memory_limit')
        );

        $max_size = $max_size_mb * 1024 * 1024;

        $error = ($max_size_mb > 0 && $file_size > $max_size)
            ? UPLOAD_ERR_INI_SIZE
            : $error;

        return $error;
    }

    protected function _RawFileToPHPFormat($file_raw, $content_length)
    {
        $error = $this->_checkFileErrors($file_raw, $content_length);

        $tmp_file = tempnam(sys_get_temp_dir(), 'avatar_');
        file_put_contents($tmp_file, $file_raw);

        $file_obj = new File($tmp_file);
        $mime_type = $file_obj->mime();

        $file_name = implode('.', [
            $file_obj->name(),
            (new MimeTypes())->getExtension($mime_type)
        ]);

        return [
            'name'     => $file_name,
            'type'     => $mime_type,
            'tmp_name' => $tmp_file,
            'error'    => $error,
            'size'     => $content_length
        ];
    }

}
