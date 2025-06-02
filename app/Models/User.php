<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'imageUrl',
        'description',
        'password',
        'otp',
        'otp_expires_at',
        'email_verified_at',
        'facebook_id',
        'google_id',
        'issue',
        'deviceToken',
        //'deleted_at',
    ];
    protected $dates = ['deleted_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = ['password', 'remember_token', 'otp'];

    public function notifications()
    {
        return $this->hasMany(Notifications::class);
    }

    public function followers()
    {
        //I give id of person who is followed ,I get all his followers
        return $this->belongsToMany(User::class, 'follower_followings', 'following_id', 'follower_id');
    }
    public function story()
    {
        //I give id of person who is followed ,I get all his followers
        return $this->hasMany(Story::class, 'user_id');
    }

    public function following()
    {
        //related model
        return $this->belongsToMany(User::class, 'follower_followings', 'follower_id', 'following_id');
    }
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    //tagging
    public function postsMany()
    {
        return $this->belongsToMany(Post::class);
    }
    public function likes()
    {
        return $this->hasMany(Like::class);
    }
    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
    }
    public function savedPosts()
    {
        return $this->belongsToMany(Post::class, 'saved_posts');
    }

    public function sharedPosts()
    {
        return $this->belongsToMany(Post::class, 'shares');
    }
    public function views()
    {
        return $this->hasMany(VideoViews::class);
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function chatparticipants()
    {
        return $this->hasMany(ChatParticipants::class, 'user_id', 'id');
    }
    public function messages()
    {
        return $this->hasMany(Messages::class);
    }
    public function chats()
    {
        // return $this->hasManyThrough(
        //     Chats::class,                  // Target model
        //     ChatParticipants::class,        // Intermediate model
        //     'user_id',                    // Foreign key on the ChatParticipants table (that refers to users) - this is the key in the `ChatParticipants` table that refers to the `User` table.
        //     'id',                         // Foreign key on the Chats table - this is the key in the `Chats` table that links back to the `ChatParticipants` table (in this case, 'id').
        //     'id',                         // Local key on the User table - this is the primary key of the `User` table (usually 'id').
        //     'chat_id'                     // Local key on the ChatParticipants table - this is the key in the `ChatParticipants` table that refers to the `Chats` table.
        // );
        return $this->hasManyThrough(
            Chats::class, // Target model (Chats)
            'chat_participants', // Pivot table
            'user_id', // Foreign key on pivot table for User
            'chat_id', // Foreign key on pivot table for Chats
        )->withPivot('role', 'status');
    }
    public function seen()
    {
        return $this->hasMany(UserSeen::class);
    }
    public function isParticipantInChat($chatId)
    {
        // Check if the user is a participant in the chat
        return $this->chatparticipants()->where('chat_id', $chatId)->exists();
    }
    public function paymentsAsCustomer()
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }
    public function paymentsAsSeller()
    {
        return $this->hasMany(Payment::class, 'seller_id');
    }
    public function phoneN()
    {
        return $this->hasOne(Phone::class);
    }
    public function bankAccount()
    {
        return $this->hasOne(BankDetails::class, 'user_id');
    }
    public function payout()
    {
        return $this->hasMany(Payouts::class, 'beneficiary_id');
    }
}
