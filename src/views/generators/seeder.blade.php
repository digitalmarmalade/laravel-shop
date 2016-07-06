<?php echo '<?php' ?>

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

/**
 * Seeds database with shop data.
 */
class LaravelShopSeeder extends Seeder
{

  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {

    DB::table('{{ $orderStatusTable }}')->delete();

    DB::table('{{ $orderStatusTable }}')->insert([
        [
            'code' 				=> 'in_creation',
            'name' 				=> 'In creation',
            'description' => 'Order being created.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'code' 				=> 'pending',
            'name' 				=> 'Pending',
            'description' => 'Created / placed order pending payment or similar.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'code' 				=> 'in_process',
            'name' 				=> 'In process',
            'description' => 'Completed order in process of shipping or revision.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'code' 				=> 'completed',
            'name' 				=> 'Completed',
            'description' => 'Completed order. Payment and other processes have been made.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'code' 				=> 'failed',
            'name' 				=> 'Failed',
            'description' => 'Failed order. Payment or other process failed.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'code' 				=> 'canceled',
            'name' 				=> 'Canceled',
            'description' => 'Canceled order.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
    ]);

  }
}