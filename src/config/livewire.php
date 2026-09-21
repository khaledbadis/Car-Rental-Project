<?php

return ['temporary_file_upload' => ['disk' => 'local', 'rules' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', 'middleware' => ['auth', 'account', 'throttle:60,1'], 'directory' => 'livewire-tmp', 'cleanup' => true]];
