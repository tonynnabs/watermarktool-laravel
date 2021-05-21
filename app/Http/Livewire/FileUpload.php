<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FileUpload extends Component
{
    use WithFileUploads;

    public $photo;
    public $showDownloadModal = false;
    public $downloadUrl ='';


    public function save()
    {

        $this->validate([
            'photo' => 'image|max:25000',
        ]);

        ini_set('max_execution_time', 600);
        $path = $this->photo->store('watermark', 's3');
        $photoUrl = Storage::disk('s3')->url($path);

        $adapter = Storage::disk('s3')->getAdapter();
        $client = $adapter->getClient();
        $client->registerStreamWrapper();

        $object = $client->headObject([
            'Bucket' => $adapter->getBucket(),
            'Key' => /*$adapter->getPathPrefix() . */$path,
        ]);
        // dd("s3://{$adapter->getBucket()}/{$path}");
        $fileUrl ="s3://{$adapter->getBucket()}/{$path}";
        $photo = fopen("s3://{$adapter->getBucket()}/{$path}", 'r');

        // $mask = Storage::disk('watermark')->path('mask.png');
        // $maskUrl = fopen($mask, 'r');


        $object = $client->headObject([
            'Bucket' => $adapter->getBucket(),
            'Key' => /*$adapter->getPathPrefix() . */'watermark/mask.png',
        ]);

        $maskPath = "s3://{$adapter->getBucket()}/watermark/mask.png";
        $maskUrl = Storage::disk('s3')->url('/watermark/mask.png');
        $mask = fopen($maskPath, 'r');

        /**uploading image and mask to server */
        $response = Http::attach(
            'image', $photo, $path
        )->attach(
            'mask', $mask, '/watermark/mask.png'
        )->post('http://3.20.22.15:8080/predict')->json();

        /** formating response to a proper JSON format */
        $response = $this->formatResponse($response);

        /** parsing JSON file to get image url */
        $response = json_decode($response, true);
        foreach($response['watermarks'] as $image => $path){
            $this->downloadUrl = $path['output_image'];
        }
        $this->showDownloadModal = true;
        Storage::disk('watermark')->delete($path);
        $this->photo = '';

    }


    private function formatResponse($response)
    {
        $response = str_replace("'", '"', $response);
        $response = str_replace("T", 't', $response);
        return $response;
    }



    public function render()
    {
        return view('livewire.file-upload');
    }
}
