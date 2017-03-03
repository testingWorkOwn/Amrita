<?php

// declare(strict_types=1);

namespace app\modules\newsletter\console;

use app\modules\newsletter\models\Newsletter;
use app\modules\object\models\Object;
use app\modules\subscription\models\Category;
use app\modules\subscription\models\query\SubscriptionQuery;
use app\modules\subscription\models\Subscription;
use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\helpers\Url;
use yii\log\Logger;

/**
 * Class LetterController
 * @package app\modules\newsletter\console
 */
class LetterController extends Controller
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        ini_set('max_execution_time', 24 * 60 * 60);
    }

    /**
     * @return mixed
     */
    public function actionSend()
    {
        try {

            $objectModel = Object::find()->status(Object::STATUS_RUN)->limit(1)->one();

            if (is_null($objectModel)) {
                $objectModel = Object::find()->status(Object::STATUS_JOB)->delay()->limit(1)->one();

                if (is_null($objectModel)) {
                    $this->stdout('EMPTY' . PHP_EOL);
                } else {
                    $this->stdout('JOB' . PHP_EOL);
                    $this->sentJob($objectModel);
                }
            } else {
                $this->stdout('PROCESS' . PHP_EOL);
                $objectModel->saveStatus(Object::STATUS_PROCESS);
                $this->sentLetter($objectModel);
            }

        } catch (\Exception $e) {

            $this->stdout('Exception' . PHP_EOL);

            /* @var $objectModel \app\modules\object\models\Object */

            /**
             * Если ошибка, увеличиваем счетчик ошибок на один
             */
            $objectModel->updateCounters(['attempt_counter' => 1]);

            $this->stdout('attempt counter:' . $objectModel->attempt_counter . PHP_EOL);
            /**
             * Если кол-во ошибок достигло установленного максимума,
             * отменяем рассылку и устанавливаем статус Letter::STATUS_FAIL
             */
            if ($objectModel->attempt_counter >= $objectModel::ATTEMPT) {
                $this->stdout('FAIL' . PHP_EOL);
                $objectModel->status = Object::STATUS_FAIL;
            } else {
                $this->stdout('RUN' . PHP_EOL);
                $objectModel->status = Object::STATUS_RUN;
            }

            if (!$objectModel->save()) {
                Yii::getLogger()->log($objectModel->getErrors(), Logger::LEVEL_ERROR);
            }

            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * @param $objectModel \app\modules\object\models\Object
     * @return int
     */
    private function sentJob(\app\modules\object\models\Object $objectModel)
    {
        $this->stdout('PROCESS' . PHP_EOL);
        $objectModel->saveStatus(Object::STATUS_PROCESS);

        $BatchCategory = $objectModel
            ->getSubscriptionCategory()
            ->joinWith(
                [
                    'subscription' => function (SubscriptionQuery $query) {
                        return $query->active(Subscription::ACTIVE);
                    }
                ],
                true,
                'RIGHT JOIN'
            )->batch();

        foreach ($BatchCategory as $categoryModels) {
            /** @var Category $categoryModel */
            foreach ($categoryModels as $categoryModel) {
                $newsletterModel = new Newsletter(
                    [
                        'object_id' => $objectModel->id,
                        'subscription_id' => $categoryModel->subscription_id,
                    ]
                );
                if (!$newsletterModel->save()) {
                    Yii::getLogger()->log($newsletterModel->getErrors(), Logger::LEVEL_ERROR);
                }
            }
        }
        $this->sentLetter($objectModel);
    }

    /**
     * @param $objectModel \app\modules\object\models\Object
     * @return int
     */
    private function sentLetter(\app\modules\object\models\Object $objectModel)
    {
        $this->stdout('Категория: ' . $objectModel->category . PHP_EOL);
        $newsletterModel = $objectModel
            ->getNewsletter()
            ->status(Newsletter::STATUS_OPENED)
            ->each(Newsletter::COUNT);


        $newsletterModel->rewind();
        if ($newsletterModel->valid() == false) {
            $this->stdout('SUCCESS' . PHP_EOL);
            $objectModel->saveStatus(Object::STATUS_SUCCESS);
        } else {

            /* @var $mailer \yii\swiftmailer\Mailer */
            $mailer = Yii::createObject([
                'class' => 'yii\swiftmailer\Mailer',
                'transport' => Yii::$app->params['transport_DEPR'],
            ]);

            $count = 0;

            foreach ($newsletterModel as $model) {

                $model->status = Newsletter::STATUS_CLOSED;

                /* @var $message \yii\swiftmailer\Message */

                $message = $mailer
                    ->compose(
                        '@app/modules/newsletter/mail/letterDEPR',
                        [
                            'text' => $objectModel->send_html,
                            'unSubscribe' =>
                                Url::to(
                                    [
                                        '/subscription/subscription/unsubscribe',
                                        'token' => $model->subscription->token
                                    ],
                                    true
                                )
                        ]
                    )
                    ->setFrom(
                        [
                            Yii::$app->params['setFrom'] => $objectModel->category
                        ]
                    )
                    ->setTo($model->subscription->email)
                    ->setSubject($objectModel->title);

                if ($message->send()) {
                    $this->stdout('Отправлено : ' . $model->subscription->email . PHP_EOL);
                } else {
                    $this->stdout('Не отправлено : ' . $model->subscription->email . PHP_EOL);
                }

                if (!$model->save()) {
                    Yii::getLogger()->log($model->getErrors(), Logger::LEVEL_ERROR);
                }

                $count++;

                if ($count == $model::COUNT) {
                    break;
                }
            }
            $this->stdout('RUN' . PHP_EOL);
            $objectModel->saveStatus(Object::STATUS_RUN);
        }
    }
}