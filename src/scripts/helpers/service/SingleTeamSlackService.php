<?php

namespace AlfredSlack\Helpers\Service;

use AlfredSlack\Libs\Utils;

use AlfredSlack\Models\ModelFactory;

use AlfredSlack\Helpers\Core\CustomCommander;
use AlfredSlack\Helpers\Http\MultiCurlInteractor;

use Frlnc\Slack\Http\SlackResponseFactory;

class SingleTeamSlackService implements SlackServiceInterface {

    private static $instances = [];

    private $commander;
    public $teamId;

	public function __construct ($teamId) {
        $this->teamId = $teamId;
        $this->initCommander();
    }

    private function initCommander () {
        $interactor = new MultiCurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);
        $token = $this->getToken($this->teamId);
        if (!empty($token)) {
            $this->commander = new CustomCommander($token, $interactor);
        }
    }

    public function getProfileIcon (\AlfredSlack\Models\UserModel $user) {
        $userId = $user->getId();
        $icon = Utils::getWorkflows()->readPath('user.image.'.$userId);
        if ($icon === false) {
            $users = $this->getUsers(true);
            $user = Utils::find($users, [ 'id' => $userId ]);
            if (!is_null($user)) {
                Utils::getWorkflows()->write(file_get_contents($user->profile->image_24), 'user.image.'.$userId);
                $icon = Utils::getWorkflows()->readPath('user.image.'.$userId);
            }
        }
        return $icon;
    }

    public function getFileIcon (\AlfredSlack\Models\FileModel $file) {
        $fileId = $file->getId();
        $icon = Utils::getWorkflows()->readPath('file.image.'.$fileId);
        if ($icon === false) {
            $files = $this->getFiles();
            $file = Utils::find($files, [ 'id' => $fileId ]);
            if (is_null($file)) {
                $file = $this->getFile($fileId);
            }
            if (!is_null($file) && property_exists($file, 'thumb_64')) {
                Utils::getWorkflows()->write(file_get_contents($file->thumb_64), 'file.image.'.$fileId);
                $icon = Utils::getWorkflows()->readPath('file.image.'.$fileId);
            }
        }
        return $icon;
    }

    private function getAuth () {
        $auth = Utils::getWorkflows()->read('auth');
        if ($auth === false) {
            $auth = $this->commander->execute('auth.test')->getBody();
            Utils::getWorkflows()->write($auth, 'auth');
            $auth = Utils::getWorkflows()->read('auth');
        }
        return $auth;
    }

    public function getChannels ($excludeArchived = false) {
        $channels = Utils::getWorkflows()->read('channels');
        if ($channels === false) {
            $params = [];
            if ($excludeArchived === true) {
                $params['exclude_archived'] = '1';
            }
            $channels = $this->commander->execute('channels.list', $params)->getBody()['channels'];
            $auth = $this->getAuth();
            foreach ($channels as $index => $channel) {
                $channels[$index] = Utils::extend($channel, [ 'auth' => $auth ]);
            }
            Utils::getWorkflows()->write($channels, 'channels');
            $channels = Utils::getWorkflows()->read('channels');
        }
        return ModelFactory::getModels($channels, '\AlfredSlack\Models\ChannelModel');
    }

    public function getGroups ($excludeArchived = false) {
        $groups = Utils::getWorkflows()->read('groups');
        if ($groups === false) {
            $params = [];
            if ($excludeArchived === true) {
                $params['exclude_archived'] = '1';
            }
            $groups = $this->commander->execute('groups.list', $params)->getBody()['groups'];
            $auth = $this->getAuth();
            foreach ($groups as $index => $group) {
                $groups[$index] = Utils::extend($group, [ 'auth' => $auth ]);
            }
            Utils::getWorkflows()->write($groups, 'groups');
            $groups = Utils::getWorkflows()->read('groups');
        }
        return ModelFactory::getModels($groups, '\AlfredSlack\Models\GroupModel');
    }

    public function getIms ($excludeDeleted = false) {
        $ims = Utils::getWorkflows()->read('ims');
        if ($ims === false) {
            $ims = $this->commander->execute('im.list')->getBody()['ims'];
            if ($excludeDeleted === true) {
                $ims = Utils::filter($ims, [ 'is_user_deleted' => false ]);
            }
            Utils::getWorkflows()->write($ims, 'ims');
            $ims = Utils::getWorkflows()->read('ims');
        }
        return ModelFactory::getModels($ims, '\AlfredSlack\Models\ImModel');
    }

    public function openIm (\AlfredSlack\Models\UserModel $user) {
        $userId = $user->getId();
        if (!isset($userId)) {
            throw new Exception('The parameter "userId" is mandatory.');
        }
        return Utils::toObject($this->commander->execute('im.open', [ 'user' => $userId ])->getBody());
    }

    public function getUsers ($excludeDeleted = false) {
        $users = Utils::getWorkflows()->read('users');
        if ($users === false) {
            $users = $this->commander->execute('users.list')->getBody()['members'];
            $auth = $this->getAuth();
            foreach ($users as $index => $user) {
                $users[$index] = Utils::extend($user, [ 'auth' => $auth ]);
            }
            Utils::getWorkflows()->write($users, 'users');
            $users = Utils::getWorkflows()->read('users');
        }
        if ($excludeDeleted === true) {
            $users = Utils::filter($users, [ 'deleted' => false ]);
        }
        return ModelFactory::getModels($users, '\AlfredSlack\Models\UserModel');
    }

    public function getFiles () {
        $files = Utils::getWorkflows()->read('files');
        if ($files === false) {
            $files = $this->commander->execute('files.list')->getBody()['files'];
            Utils::getWorkflows()->write($files, 'files');
            $files = Utils::getWorkflows()->read('files');
        }
        return $files;
    }
    
    public function getFile (\AlfredSlack\Models\FileModel $file) {
        return ModelFactory::getModel($this->commander->execute('files.info', [ 'file' => $file->getId() ])->getBody()['file'], '\AlfredSlack\Models\FileModel');
    }

    public function getStarredItems () {
        $stars = Utils::getWorkflows()->read('stars');
        if ($stars === false) {
            $stars = $this->commander->execute('stars.list')->getBody()['items'];
            Utils::getWorkflows()->write($stars, 'stars');
            $stars = Utils::getWorkflows()->read('stars');
        }
        return $stars;
    }

    public function search ($query) {
        return $this->commander->execute('search.all', [ 'query' => $query ])->getBody();
    }

    public function getImIdByUserId (\AlfredSlack\Models\UserModel $user) {
        $userId = $user->getId();
        // Get the IM id if a user
        $ims = $this->getIms(true);
        $im = Utils::find($ims, [ 'user' => $userId ]);
        if (!empty($im)) {
            return $im->getId();
        } else {
            $im = $this->openIm($userId);
            return $im->channel->id;
        }
    }

    private function getToken ($teamId) {
        $token = Utils::getWorkflows()->read('token.'.$teamId);
        if ($token === false) {
            return Utils::getWorkflows()->getPassword('token.'.$teamId);
        } else {
            return $token;
        }
    }

    public function setPresence ($isAway = false) {
        $this->commander->execute('users.setPresence', [ 'presence' => $isAway ? 'away' : 'auto' ])->getBody();
    }

    public function postMessage (\AlfredSlack\Models\ChannelModel $channel, $message, $asBot = false) {
        return $this->commander->execute('chat.postMessage', [
            'channel' => $channel,
            'text' => $message,
            'as_user' => !$asBot,
            'parse' => 'full',
            'link_names' => 1,
            'unfurl_links' => true,
            'unfurl_media' => true
        ])->getBody();
    }

    public function getChannelHistory (\AlfredSlack\Models\ChannelModel $channel) {
        return ModelFactory::getModels($this->commander->execute('channels.history', [ 'channel' => $channel->getId() ])->getBody()['messages'], '\AlfredSlack\Models\MessageModel');
    }

    public function getGroupHistory (\AlfredSlack\Models\GroupModel $group) {
        return ModelFactory::getModels($this->commander->execute('groups.history', [ 'channel' => $group->getId() ])->getBody()['messages'], '\AlfredSlack\Models\MessageModel');
    }

    public function getImHistory (\AlfredSlack\Models\ImModel $im) {
        return ModelFactory::getModels($this->commander->execute('im.history', [ 'channel' => $im->getId() ])->getBody()['messages'], '\AlfredSlack\Models\MessageModel');
    }

    public function refreshCache () {

        // Refresh auth
        Utils::getWorkflows()->delete('auth');
        $this->getAuth();

        // Refresh channels
        Utils::getWorkflows()->delete('channels');
        $this->getChannels();
        
        // Refresh groups
        Utils::getWorkflows()->delete('groups');
        $this->getGroups();
        
        // Refresh user icons
        foreach ($this->getUsers() as $user) {
            Utils::getWorkflows()->delete('user.image.' . $user->id);
            $this->getProfileIcon($user->id);
        }

        // Refresh users
        Utils::getWorkflows()->delete('users');
        $this->getUsers();
        
        // Refresh file icons
        foreach ($this->getFiles() as $file) {
            Utils::getWorkflows()->delete('file.image.' . $file->id);
            $this->getFileIcon($file->id);
        }
        
        // Refresh ims
        Utils::getWorkflows()->delete('ims');
        $this->getIms();

    }

    public function markChannelAsRead (\AlfredSlack\Models\ChannelModel $channel) {
        $now = time();
        $this->commander->executeAsync('channels.mark', [ 'channel' => $channel->getId(), 'ts' => $now ]);
    }

    public function markGroupAsRead (\AlfredSlack\Models\GroupModel $group) {
        $now = time();
        $this->commander->executeAsync('groups.mark', [ 'channel' => $group->getId(), 'ts' => $now ]);
    }

    public function markImAsRead (\AlfredSlack\Models\ImModel $im) {
        $now = time();
        $this->commander->executeAsync('im.mark', [ 'channel' => $im->getId(), 'ts' => $now ]);
    }

    public function markAllAsRead () {
        $now = time();
        $requests = [];
        
        $channels = $this->getChannels();
        foreach ($channels as $channel) {
            $requests[] = [ 'command' => 'channels.mark', 'parameters' => [ 'channel' => $channel->getId(), 'ts' => $now ] ];
        }

        $groups = $this->getGroups();
        foreach ($groups as $group) {
            $requests[] = [ 'command' => 'groups.mark', 'parameters' => [ 'channel' => $group->getId(), 'ts' => $now ] ];
        }

        $ims = $this->getIms();
        foreach ($ims as $im) {
            $requests[] = [ 'command' => 'im.mark', 'parameters' => [ 'channel' => $im->getId(), 'ts' => $now ] ];
        }

        return array_map(function ($e) { return $e->getBody(); }, $this->commander->executeAll($requests));
    }
}