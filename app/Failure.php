<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Failure extends Model
{
	use Searchable;

	/**
	 * The attributes that should be mutated to dates.
	 * @var array
	 */
	protected $dates = [
		'created_at',
		'updated_at',
		'first_failed_at',
		'last_failed_at',
	];

	/**
	 * Validation rules
	 * @var array
	 */
	public static $rules = [
		'email_id' => 'required|exists:emails,id',
		'subscriber_id' => 'required|exists:subscribers,id',
		'type' => 'required|max:255',
		'reason' => 'max:512'
	];

	/**
	 * A Click belongs to an Email
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function email()
	{
		return $this->belongsTo(Email::class);
	}

	/**
	 * A Click belongs in a Subscriber
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function subscriber()
	{
		return $this->belongsTo(Subscriber::class);
	}

	/**
	 * Find resource by id
	 * @param $id
	 *
	 * @return \Illuminate\Database\Eloquent\Collection|Model|null|static|static[]
	 */
	public static function findResource($id)
	{
		return static::with('email')->with('subscriber')->find($id);
	}

	/**
	 * Find resource with given conditions
	 * @param $email_id
	 * @param $subscriber_id
	 * @param $type
	 *
	 * @return Model|null|static
	 */
	public static function findResourceBelongingTo($email_id, $subscriber_id, $type)
	{
		return static::with('email')
		             ->with('subscriber')
		             ->where('email_id', $email_id)
		             ->where('subscriber_id', $subscriber_id)
		             ->where('type', $type)
		             ->first();
	}

}
