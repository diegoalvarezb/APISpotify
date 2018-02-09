<?php

namespace App\Http\Controllers\Spotify;

use App\Http\Controllers\Controller;
use App\SpotifyProfile;
use Carbon\Carbon;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use Ramsey\Uuid\Uuid;

class SpotifySessionController extends Controller
{
    public $sessionSpotify;
    public $spotifyAccessToken;
    public $spotifyRefreshToken;
    public $spotifyTokenExpirationTime;

    public $spotifyWebAPI;

    public function __construct() {
        $this->sessionSpotify = new Session(
            env('SPOTIFY_CLIENT_ID'),
            env('SPOTIFY_CLIENT_SECRET'),
            env('SPOTIFY_URI_CALLBACK')
        );
    }

    public function authSpotifySession() {
        $options = [
            'scope' => [
                'user-read-private',
                'user-top-read',
                'user-read-recently-played',
                'user-read-currently-playing',
                'user-read-email'
            ]
        ];

        return redirect($this->sessionSpotify->getAuthorizeUrl($options));
    }

    public function callback() {

        $this->sessionSpotify->requestAccessToken($_GET['code']);
        $this->spotifyAccessToken = $this->sessionSpotify->getAccessToken();
        $this->spotifyRefreshToken = $this->sessionSpotify->getRefreshToken();
        $this->spotifyTokenExpirationTime = $this->sessionSpotify->getTokenExpiration();

        $this->saveSpotifyProfile();

        return redirect('/');
    }

    public function loadSpotifyAPI() {

        $this->spotifyWebAPI = new SpotifyWebAPI();
        $spotifyProfile = SpotifyProfile::where('accessToken', '=', $this->spotifyAccessToken)->get()->first();

        if(NULL !== $spotifyProfile && ((int)$spotifyProfile->expirationToken <= Carbon::now()->timestamp)) {
            $this->refreshToken($this->spotifyRefreshToken);
        }

    }

    public function saveSpotifyProfile() {

        $this->loadSpotifyAPI();
        $this->spotifyWebAPI->setAccessToken($this->spotifyAccessToken);

        $request = $this->spotifyWebAPI->me();

        $fields = [
            'id' => Uuid::uuid1()->toString(),
            'nick' => $request->id,
            'email' => $request->email,
            'display_name' => $request->display_name,
            'country' => $request->country,
            'href' => $request->href,
            'image_url' => empty($request->images)?:$request->images[0]->url,
            'accessToken' => $this->spotifyAccessToken,
            'refreshToken' => $this->spotifyRefreshToken,
            'expirationToken' => $this->spotifyTokenExpirationTime,
        ];

        SpotifyProfile::updateOrCreate([ 'email' => $request->email ], $fields);

    }

    public function refreshToken($refreshToken) {

        if($this->sessionSpotify->refreshAccessToken($refreshToken)){
            $this->spotifyAccessToken = $this->sessionSpotify->getAccessToken();
            $this->spotifyRefreshToken = $this->sessionSpotify->getRefreshToken();
            $this->spotifyTokenExpirationTime = $this->sessionSpotify->getTokenExpiration();

            $this->saveSpotifyProfile();
        }

    }

    public static function clientCredentials(){
        $session = new Session(
            env('SPOTIFY_CLIENT_ID'),
            env('SPOTIFY_CLIENT_SECRET')
        );

        $session->requestCredentialsToken();

        return $session->getAccessToken();
    }

}