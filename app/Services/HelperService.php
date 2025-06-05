<?php

namespace App\Services;

use App\Models\ChatParticipants;
use App\Models\Chats;
use App\Models\Messages;
use App\Models\Notifications;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;
use GuzzleHttp\Psr7\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class HelperService
{
    protected $firebaseService;
    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }
    public function mailService($otp, $user)
    {
        return Mail::raw("Your OTP is $otp. It will be valid for the next 15 minutes", function ($message) use ($user) {
            $message->to($user->email)->subject('Your OTP');
        });
    }
    public function awsAdd($files, $directory)
    {
        $uploadedFiles = [];
        try {
            foreach ($files as $file) {
                //get extension
                $originalExtension = $file->getClientOriginalExtension();
                //extensions for image
                $isImage = in_array($originalExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                //extensions for video
                $isVideo = in_array($originalExtension, ['mp4', 'avi', 'mov', 'flv', 'wmv']);

                if ($isImage) {
                    //including new extension in file along with name
                    $filePath = $this->convertImageToJpg($file, $directory);
                    $uploadedFiles[] = [
                        'aws_link' =>  '/' . $filePath,
                        //Storage::url($filePath),
                        'thumbnail' => '',
                    ];
                } elseif ($isVideo) {
                    // $outputPath = storage_path('app/temp/' . $name . '.mp4');
                    $filePath = $this->convertVideoMP4($file, $directory);

                    $thumbnail = $this->generateVideoThumbnail($file, $directory);
                    $uploadedFiles[] = [
                        'aws_link' =>  '/' . $filePath,
                        //Storage::url($filePath),
                        'thumbnail' =>  '/' . $thumbnail,
                    ];
                } else {
                    continue;
                }
            }
            return $uploadedFiles;
        } catch (\Throwable $th) {
            // return HelperResponse("error","Error found",500,$th);
            Log::error('File upload error: ' . $th->getMessage());
            return $th;
        }
    }
    public function onlyUpload($files, $directory)
    {

        $filename = uniqid() . '.' . $files[0]->getClientOriginalExtension();
        $fullPath = $directory . '/' . $filename;
        $fileurl = Storage::disk('s3')->put($fullPath, file_get_contents($files[0]), 'public');
        $fileurl = '/' . $fullPath;
        //----------------------------------------------------------
        $s3Thumbnail = $directory . '/thumbnails';

        $filename = uniqid() . '.' . $files[1]->getClientOriginalExtension();

        $fullPath = $s3Thumbnail . '/' . $filename;

        // Save it properly
        Storage::disk('s3')->put($fullPath, file_get_contents($files[1]), 'public');
        // Get the URL
        $thumbfileurl = '/' . $fullPath;
        return  [
            [
                'aws_link' =>  $fileurl,
                'thumbnail' =>    $thumbfileurl,
            ]
        ];
    }
    public function uploadOnlyFile($file, $directory)
    {
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $fullPath = $directory . '/' . $filename;
        $fileurl = Storage::disk('s3')->put($fullPath, file_get_contents($file), 'public');
        $fileurl = '/' . $fullPath;
        return $fileurl;
    }
    public function uploadOnlyThumbnail($thumbFile, $directory)
    {
        $s3Thumbnail = $directory . '/thumbnails';

        $filename = uniqid() . '.' . $thumbFile->getClientOriginalExtension();
        $fullPath = $s3Thumbnail . '/' . $filename;

        // Save it properly
        Storage::disk('s3')->put($fullPath, file_get_contents($thumbFile), 'public');
        // Get the URL
        $thumbfileurl = '/' . $fullPath;
        return $thumbfileurl;
    }
    public function uploadFilesAnThumbnail($files, $thumbnails, $directory)
    {
        try {
            $len = count($files);
            $j = 0;
            $uploadedFiles = [];
            $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'webm', '3gp', 'm4v', 'ts', 'f4v'];

            // $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            for ($i = 0; $i < $len; $i++) {
                $extension = strtolower($files[$i]->getClientOriginalExtension());
                $awsLink = $this->uploadOnlyFile($files[$i], $directory);

                if (in_array($extension, $videoExtensions)) {
                    $thumbnailLink = $this->uploadOnlyThumbnail($thumbnails[$j], $directory);
                    $j++;
                } else {
                    $thumbnailLink = '';
                }

                $uploadedFiles[] = [
                    'aws_link' => $awsLink,
                    'thumbnail' => $thumbnailLink,
                ];
            }
            return $uploadedFiles;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
    public function awsDelete($UploadedFilesArray)
    {
        try {
            if (!$UploadedFilesArray || $UploadedFilesArray->isEmpty()) {
                return ["message" => "Nothing to delete", "status" => true];
            }
            foreach ($UploadedFilesArray as $obj) {
                Storage::disk('s3')->delete($obj->aws_link);
                if (!empty($obj->thumbnail)) {
                    Storage::disk('s3')->delete($obj->thumbnail);
                }
                $obj->delete();
            }
            return ["message" => "success", "status" => true,];
        } catch (\Throwable $th) {
            return ["message" => $th->getMessage(), "status" => false,];
        }
    }
    public function convertImageToJpg($file, $directory)
    {
        if (!is_null($file)) {
            $name = time() . basename($file->getClientOriginalName(), '.' . $file->getClientOriginalExtension());
            $filePath = $directory . $name . '.jpg';

            $manager = new ImageManager(new Driver());
            $image = $manager->read($file);
            //converting file to jpg
            $encoded = $image->toJpeg();
            Storage::disk('s3')->put($filePath, $encoded);
            return $filePath;
        } else {
            // Handle the case where $file is null (e.g., log an error)
            report(new Exception('No file provided for conversion.'));
            return null; // Or return a default value as needed
        }
    }
    public function convertVideoMP4($file, $directory)
    {
        try {
            $name = time() . basename($file->getClientOriginalName(), '.' . $file->getClientOriginalExtension());
            // Specify the paths to FFmpeg and FFProbe if necessary
            $ffmpegPath = 'C:\ffmpeg\ffmpeg-master-latest-win64-gpl\bin\ffmpeg.exe';
            $ffprobePath = 'C:\ffmpeg\ffmpeg-master-latest-win64-gpl\bin\ffprobe.exe';

            // Initialize FFMpeg
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => $ffmpegPath,
                'ffprobe.binaries' => $ffprobePath,
            ]);

            // Open the input video
            $video = $ffmpeg->open($file->getPathname());
            // Define the output path
            $outputPath = storage_path('app/public/' . $name . '.mp4');
            // Choose the format and configure it
            $format = new X264('aac', 'libx264');
            // Save the video in the new format
            $video->save($format, $outputPath);
            // Upload to S3
            $s3Path = $directory . $name . '.mp4';
            $fileStream = fopen($outputPath, 'r+');

            if ($fileStream === false) {
                throw new \Exception('Unable to open local file.');
            }
            Storage::disk('s3')->put($s3Path, $fileStream);
            fclose($fileStream);
            // Delete the local file
            unlink($outputPath);
            return $s3Path;
        } catch (\Exception $e) {
            Log::error('Error converting video or generating thumbnail: ' . $e->getMessage());
            throw $e;
        }
    }
    public function generateVideoThumbnail($file, $directory)
    {
        try {
            $thumbnailName = basename($file->getClientOriginalName(), '.' . $file->getClientOriginalExtension()) . '.jpg';
            //Assuming you have FFmpeg installed and accessible via the command line
            $videoPath = $file->getRealPath();

            //storage/app/thumbnails/filename.jpg
            $thumbnailFullPath = storage_path('app/public/' . $thumbnailName);
            // Specify the paths to FFmpeg and FFProbe if necessary
            $ffmpegPath = 'C:\ffmpeg\ffmpeg-master-latest-win64-gpl\bin\ffmpeg.exe';
            $ffprobePath = 'C:\ffmpeg\ffmpeg-master-latest-win64-gpl\bin\ffprobe.exe';
            //Generate the thumbnail using FFmpeg
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => $ffmpegPath,
                'ffprobe.binaries' => $ffprobePath,
            ]);

            $video = $ffmpeg->open($videoPath);
            $video->frame(TimeCode::fromSeconds(1))->save($thumbnailFullPath);
            //upload the thumbnail to AWS S3
            $s3Path = $directory . 'thumbnails/' . $thumbnailName;
            Storage::disk('s3')->put($s3Path, file_get_contents($thumbnailFullPath));

            //get url of uploaded thumbnail
            // $s3Url = Storage::url($s3Path);

            //Delete the local thumbnail file
            unlink($thumbnailFullPath);
            return $s3Path;
        } catch (\Throwable $th) {
            Log::error('Error converting video or generating thumbnail: ' . $th->getMessage());
            throw $th;
        }
    }
    public function deleteThumbnail($url)
    {
        try {
            Storage::disk('s3')->delete($url);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    public function breakDeviceToken($deviceToken)
    {
        $parts = explode(' br ', $deviceToken);

        return $parts; //[trim($parts[0]),trim($parts[1]),];
    }
    public function testing($owner, $newlike, $notifObj)
    {
        if ($owner->deviceToken != null) {
            $deviceToken = $owner->deviceToken;
            $parts = $this->breakDeviceToken($deviceToken);
            $loggedUserPart = $this->breakDeviceToken(Auth::user()->deviceToken);
            if ($parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                $this->firebaseService->likeCommentNotification(
                    $parts[0],
                    $notifObj->get('title'),
                    $notifObj->get('body'),
                    $notifObj->get('file'),
                );
                $notif = Notifications::create([
                    'notif_parent_id' => $newlike->id,
                    'notif_parent_type' => get_class($newlike),
                ]);
            }
        }
    }
    public function getMessageCount($userID)
    {
        try {
            $loggedUser = User::find($userID);
            $chatIDs = $loggedUser->chatparticipants()->where('status', 'accepted')->pluck('chat_id');
            $message = Messages::where('sender_id', "!=", $loggedUser->id)
                ->whereIn('chat_id', $chatIDs)
                ->whereDoesntHave('getSeen', function ($query) use ($loggedUser) {
                    return $query->where('user_id', $loggedUser->id);
                })
                ->get();
            return count($message);
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function messageFromCreator()
    {
        try {
            $loggeduser = Auth::user();
            $creator = User::where('email', env('MAIL_USERNAME'))->first();
            if (!$creator) {
                return [
                    "status" => "error",
                    "message" => "Creator not found",
                ];
            }
            if ($loggeduser->id == $creator->id) {
                return [
                    "status" => "error",
                    "message" => "Same user",
                ];
            }


            $chat = Chats::create([
                'is_group' => 0,
                'chat_name' => null,
            ]);
            $chatterIDS = [$creator->id, $loggeduser->id];
            $allparticipants = $chatterIDS;

            foreach ($allparticipants as $participants_id) {
                //if participant id == logged id and group == true ?"admin" else "member"
                $checkIfAdmin = 'member';
                $checkStatus = 'accepted';
                // $checkchatParticipant = ChatParticipants::where('user_id',$participants_id)->where('chat_id',$chat->id)->exists();
                ChatParticipants::create([
                    'user_id' => $participants_id,
                    'chat_id' => $chat->id,
                    'role' => $checkIfAdmin,
                    'status' => $checkStatus,
                ]);
            }
            $messages = [
                env('myMessageOne', 'Hello from the creator'),
                env('myMessageTwo', 'For any issues, email me at randeepsarma10@gmail.com'),
            ];
            $latestMessages = [];
            $baseTime = now();
            foreach ($messages as $index => $messageContent) {
                $customTimestamp = $baseTime->copy()->addSeconds($index);

                $message = new Messages([
                    'sender_id' => $creator->id,
                    'chat_id' => $chat->id,
                    'message' => $messageContent,
                    'is_missed_call' => false,
                    'media_type' => 0,
                ]);

                $message->created_at = $customTimestamp;
                $message->updated_at = $customTimestamp;
                $message->timestamps = false; // <<==== IMPORTANT (disable auto timestamps)

                $message->save();


                $latestMessages[] = [
                    'sender' => [
                        'sender_id' => $creator->id,
                        'username' => $creator->username,
                        'imageUrl' => $creator->imageUrl,
                    ],
                    'chat_id' => $message->chat_id,
                    'message' => $message->message,
                    'is_missed_call' => $message->is_missed_call,
                    'media_type' => $message->media_type,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                    'seenusers' => [],
                ];
            }

            $responseChat = [
                'recipients' => [
                    [
                        'id' => $creator->id,
                        'username' => $creator->username,
                        'name' => $creator->name,
                        'imageUrl' => $creator->imageUrl,
                        'status' => $creator->status,
                        'role' => $creator->role,
                    ]
                ],
                'id' => $chat->id,
                'is_group' => $chat->is_group,
                'groupIcon' => $chat->groupIcon,
                'chat_name' => $chat->chat_name,
                'unread_messages' =>  count($latestMessages),
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
                'lastMessages' => $latestMessages,

            ];
            return [
                "status" => "success",
                "message" => "Automated message created",
                "data" => $responseChat
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => $th->getMessage(),
            ];
        }
    }
    public function finalUser($user)
    {
        try {
            $country_code = optional($user->phoneN)->country_code;
            $phone = optional($user->phoneN)->phone;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'imageUrl' => $user->imageUrl,
                'description' => $user->description,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'posts_count' => $user->posts_count,
                'following_count' => $user->following_count,
                'followers_count' => $user->followers_count,
                'phone' => $country_code && $phone ? strval($country_code . $phone) : null,
                'bank_account' => $user->bankAccount,
            ];
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function fullUrlTransform($url)
    {
        return $url == '' ? $url : env('AWS_URL') . $url;
    }
}
