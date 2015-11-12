<?php

namespace AlfredSlack\Helpers\Service;

interface SlackServiceInterface {

	public function getProfileIcon (\AlfredSlack\Models\UserModel $user);
	public function getFileIcon (\AlfredSlack\Models\FileModel $file);
	public function getChannels ($excludeArchived);
	public function getGroups ($excludeArchived);
	public function getIms ($excludeDeleted);
	public function openIm (\AlfredSlack\Models\UserModel $user);
	public function getUsers ($excludeDeleted);
	public function getFiles ();
	public function getFile (\AlfredSlack\Models\FileModel $file);
	public function getStarredItems ();
	public function getImIdByUserId (\AlfredSlack\Models\UserModel $user);
	public function setPresence ($isAway);
	public function postMessage (\AlfredSlack\Models\ChannelModel $channel, $message, $asBot);
	public function getChannelHistory (\AlfredSlack\Models\ChannelModel $channel);
	public function getGroupHistory (\AlfredSlack\Models\GroupModel $group);
	public function getImHistory (\AlfredSlack\Models\ImModel $im);
	public function refreshCache ();
	public function markChannelAsRead (\AlfredSlack\Models\ChannelModel $channel);
	public function markGroupAsRead (\AlfredSlack\Models\GroupModel $group);
	public function markImAsRead (\AlfredSlack\Models\ImModel $im);
	public function markAllAsRead ();

}