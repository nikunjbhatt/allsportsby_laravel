<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    /**
     * In the task we need to calculate amount of hours suppliers are working during last week for marketing.
     * You can use any way you like to do it, but remember, in real life we are about to have 400+ real
     * suppliers.
     *
     * @return void
     */
    public function testCalculateAmountOfHoursDuringTheWeekSuppliersAreWorking()
    {
		$response = $this->get('/api/suppliers');
		$suppliers = \json_decode($response->getContent(), true)['data']['suppliers'];
 
		$calcTotalHoursOfDay = function(String $hours) {
			$total = 0;
			$timeSlots = explode(',', $hours); // separating time slots
			
			for($i = 0, $c = count($timeSlots); $i < $c; $i++) {
				list($open, $close) = explode('-', $timeSlots[$i]); // separating open and close time from the time slot
				
				$oDateTime = \DateTime::createFromFormat('G:i', $open, new \DateTimeZone('UTC'));
				$cDateTime = \DateTime::createFromFormat('G:i', $close, new \DateTimeZone('UTC'));

				$duration = $oDateTime->diff($cDateTime); // calculate difference between open and close time
				$minutes = ($duration->format('%h') * 60) + $duration->format('%i'); // calculate total minutes
				$total += $minutes / 60;
			}
			
			return $total;		
		};
		
		$hours = 0;
		//print_r($suppliers);
		for($i = 0, $c = count($suppliers); $i < $c; $i++) {
			// separating slots with space and sending second element of the time slot only to the function
			// (discarding first element of name of the day).
			$arHours = [
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['mon'])[1]),
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['tue'])[1]),
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['wed'])[1]),
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['thu'])[1]),
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['fri'])[1]),
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['sat'])[1]),
				$calcTotalHoursOfDay(explode(' ', $suppliers[$i]['sun'])[1])
			];
			
			$hours += array_sum($arHours);
		}

        $response->assertStatus(200);
        $this->assertEquals(136, $hours,
            "Our suppliers are working X hours per week in total. Please, find out how much they work..");
    }

    /**
     * Save the first supplier from JSON into database.
     * Please, be sure, all asserts pass.
     *
     * After you save supplier in database, in test we apply verifications on the data.
     * On last line of the test second attempt to add the supplier fails. We do not allow to add supplier with the same name.
     */
    public function testSaveSupplierInDatabase()
    {
        Supplier::query()->truncate();
        $responseList = $this->get('/api/suppliers');
        $supplier = \json_decode($responseList->getContent(), true)['data']['suppliers'][0];

        $response = $this->postJson('/api/suppliers', $supplier);

        $response->assertStatus(204);
        $this->assertEquals(1, Supplier::query()->count());
        $dbSupplier = Supplier::query()->first();
        $this->assertNotFalse(curl_init($dbSupplier->url));
        $this->assertNotFalse(curl_init($dbSupplier->rules));
        $this->assertGreaterThan(4, strlen($dbSupplier->info));
        $this->assertNotNull($dbSupplier->name);
        $this->assertNotNull($dbSupplier->district);


        $response = $this->postJson('/api/suppliers', $supplier);
        $response->assertStatus(422);
    }
}
