<?php

namespace App\Providers;

use App\Campaign;
use App\DeveloperGuide;
use App\Email;
use App\EmailContent;
use App\MailingList;
use App\Policies\CampaignPolicy;
use App\Policies\DeveloperGuidePolicy;
use App\Policies\EmailContentPolicy;
use App\Policies\EmailPolicy;
use App\Policies\MailingListPolicy;
use App\Policies\SubscriberPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\UserGuidePolicy;
use App\Policies\UserPolicy;
use App\Subscriber;
use App\Template;
use App\User;
use App\UserGuide;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
	    User::class => UserPolicy::class,
	    MailingList::class => MailingListPolicy::class,
	    Subscriber::class => SubscriberPolicy::class,
	    Campaign::class => CampaignPolicy::class,
	    Template::class => TemplatePolicy::class,
	    Email::class => EmailPolicy::class,
	    EmailContent::class => EmailContentPolicy::class,
	    UserGuide::class => UserGuidePolicy::class,
	    DeveloperGuide::class => DeveloperGuidePolicy::class,

    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }

	/**
	 * Bind into the container
	 */
	public function register()
	{
		$this->app->singleton('AuthService', function($app) {
			return new $this($app);
		});
	}

	/**
	 * For accessing all the policies later in the app
	 * @return array
	 */
	public function getPolicies()
	{
		return $this->policies;
	}
}
