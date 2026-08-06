<?php

namespace App\Console\Commands;

use App\Models\AdvisorConversation;
use App\Models\AppNotification;
use App\Models\PasswordResetOtp;
use App\Models\SyncActionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredPersonalData extends Command
{
    protected $signature = 'agroaide:purge-expired-personal-data';

    protected $description = 'Purge expired operational and personal data';

    public function handle(): int
    {
        $days = config('security.retention_days');
        PasswordResetOtp::where('created_at', '<', now()->subDays($days['otps']))->delete();
        SyncActionLog::where('created_at', '<', now()->subDays($days['sync_payload_logs']))->delete();
        AdvisorConversation::where('created_at', '<', now()->subDays($days['conversations']))->delete();
        AppNotification::where('created_at', '<', now()->subDays($days['notifications']))->delete();

        $cutoff = now()->subDays($days['exports'])->getTimestamp();
        foreach (Storage::disk('local')->allFiles('exports') as $file) {
            if (Storage::disk('local')->lastModified($file) < $cutoff) {
                Storage::disk('local')->delete($file);
            }
        }

        $tempCutoff = now()->subDays($days['temp_media'])->getTimestamp();
        foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'{plantnet_,voice_}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $tempCutoff) {
                @unlink($file);
            }
        }

        $this->info('Expired personal data purged.');

        return self::SUCCESS;
    }
}
