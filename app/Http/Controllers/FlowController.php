<?php

namespace App\Http\Controllers;

use App\Console\Commands\Interactive;
use App\Models\Answer;
use App\Models\Card;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FlowController extends Controller

{
    const NOT_ANSWERED = 'not_answered';
    const INCORRECT = 'incorrect';
    const CORRECT = 'correct';

    /**
     * @var Interactive
     * Command object
     */
    private Interactive $context;

    /**
     * @param $context
     */
    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * @return void
     * Displays the main menu
     */
    public function mainMenu():void {
        $this->context->line('1 - ' . __('menu.create_flashcard'));
        $this->context->line('2 - ' . __('menu.list_flashcards'));
        $this->context->line('3 - ' . __('menu.practice'));
        $this->context->line('4 - ' . __('menu.stats'));
        $this->context->line('5 - ' . __('menu.reset'));
        $this->context->line('6 - ' . __('menu.exit'));
    }

    /**
     * @param $response
     * @return bool
     * Validates main menu input and calls associated method, returns true for exit
     */
    public function handleMenuOption($response):bool
    {
        $validator = Validator::make(['response' => $response], ['response' => ['required','int','min:1','max:6']]);
        if (!$this->validateAndDisplayErrors($validator)) {
            return false;
        }

        switch($response) {
            case '1':
                $this->createFlashCard();
                break;
            case '2':
                $this->listFlashCards();
                break;
            case '3':
                $this->practice();
                break;
            case '4':
                $this->showStatistics();
                break;
            case '5':
                $this->reset();
                break;
        }

        return $response === '6';
    }

    /**
     * @return void
     * Creates or updates a (flash)card in database
     */
    private function createFlashCard():void {
        $question = $this->context->ask(__('interaction.question_or_enter'));
        if (!empty($question)) {
            do  {
                $answer = $this->context->ask(__('interaction.answer'));
                $validator = Validator::make(['answer' => $answer], ['answer' => ['required']]);
            } while (!$this->validateAndDisplayErrors($validator));

            $card = new Card;
            $card->question = $question;
            $card->answer = $answer;
            $card->save();
        }

    }

    /**
     * @return void
     * shows a list of flashcards from database
     */
    private function listFlashCards():void {
        $this->context->table(
            [__('headers.question'), __('headers.answer')],
            Card::all(['question', 'answer'])->toArray()
        );
    }

    /**
     * @return void
     * gets and stores an answer in the database
     */
    private function practice():void {

        while(($card = $this->selectQuestion()) !== null) {
            $question = Card::find($card);
            $answer = Answer::where('card', '=', $card)->first();

            do  {
                $response = $this->context->ask($question->question);
                $validator = Validator::make(['response' => $response], ['response' => ['required']]);
            } while (!$this->validateAndDisplayErrors($validator));

            if ($answer === null) {
               $answer = new Answer;
               $answer->card = $card;
            }
            $answer->answer = $response;
            $answer->save();

            if($response === $question->answer) {
                $this->context->info(__('interaction.correct_answer'));
            } else {
                $this->context->error(__('interaction.incorrect_answer'));
            }

        }
    }

    /**
     * @return int|null
     * Validates if a question can be answered, and if so returns the card-id of the selected question.
     * returns card-id if a valid question is selected
     */
    private function selectQuestion(): ?int
    {

        $progressTable = self::getProgressTable();

        $stats = self::getStatistics($progressTable);

        if ($stats->total === 0) { // No flashcards available
            $this->context->error(__('errors.nothing_to_do'));
            return null;
        }

        // display list of questions
        $this->context->table(['#', __('headers.question'), __('headers.result')], array_map(function ($row) {
            return [$row->seq, $row->question, $row->resultTxt];
        }, $progressTable));

        $this->context->line(__('interaction.completed', ['pct' => sprintf('%-3.1f', 100 * $stats->{self::CORRECT} / $stats->total )]));

        if ($stats->{self::INCORRECT} + $stats->{self::NOT_ANSWERED} === 0) { // no 'open' flashcards available
            $this->context->error(__('errors.all_questions_answered'));
            return null;
        }

        // create array with questions that are incorrect or not answered
        $validQuestions = array_filter(array_map(function($row) {
            if ($row->result !== self::CORRECT) {
                return strval($row->seq);
            } else {
                return null;
            }}, $progressTable));

        // add escape options
        $validQuestions[] = 'x';
        $validQuestions[] = 'X';

        do  {
            $response = $this->context->ask(__('interaction.enter_or_exit'));
            $validator = Validator::make(
                ['response' => $response],
                ['response' => ['required', Rule::in($validQuestions)]],
                ['in' => __('errors.invalid_question')],
            );
        } while (!$this->validateAndDisplayErrors($validator));

        if (strtoupper($response) === 'X') {
            return null;
        }

        // get card from progress table with the selected response number
        $cardId = array_values(array_filter($progressTable, function($row) use ($response) { return $row->seq === (int) $response;}))[0];

        return $cardId->card_id;

    }

    /**
     * @return array
     * returns array with questions and results
     */
    private static function getProgressTable():array {
        $n = 1;
        $progressTable = DB::table('cards')
            ->selectRaw('cards.id as card_id, cards.question as question, if(answers.answer IS NULL, \''  . self::NOT_ANSWERED .
                '\',if(answers.answer = cards.answer, \''. self::CORRECT. '\', \''. self::INCORRECT .'\')) as result')
            ->leftJoin('answers', 'answers.card', '=', 'cards.id')
            ->get()->toArray();
        // add some extra's to the rows
        foreach($progressTable as $row) {
            $row->seq = $n++; // add numbering for display
            $row->resultTxt = __('statistics.' . $row->result); // add translated text for display
        }
        return $progressTable;
    }

    /**
     * @param $progressTable
     * @return object
     * evaluates progresstable and calculate totals
     */
    private function getStatistics($progressTable) {
        $results = (object) [
            self::INCORRECT => 0,
            self::CORRECT => 0,
            self::NOT_ANSWERED => 0,
            'total' => 0,
        ];
        foreach($progressTable as $row) {
            $results->{$row->result}++;
            $results->total++;
        }

        return $results;
    }

    /**
     * @return void
     * Displays the statistics table
     */
    private function showStatistics():void {
        $stats = self::getStatistics(self::getProgressTable());

        $statTable = [
            [ __('statistics.total_questions'), sprintf('%-3d',$stats->total)],
            [ __('statistics.pct_answered'),
                sprintf('%-3.1f %%',100 * ( $stats->{self::CORRECT} + $stats->{self::INCORRECT}) / $stats->total)],
            [ __('statistics.pct_correct'),
                sprintf('%-3.1f %%',100 * ( $stats->{self::CORRECT}) / $stats->total)],
        ];

        $this->context->table([__('headers.statistic'),__('headers.value')], $statTable);
    }

    /**
     * @return void
     * remove all answers
     */
    private function reset():void{
        Answer::truncate();
    }

    /**
     * @param $validator
     * @return bool
     * returns true if valid, else displays error messages and returns false
     */
    private function validateAndDisplayErrors($validator):bool {
        if ($validator->fails()) {
            $this->context->error(implode(', ',$validator->errors()->all()));
            return false;
        }
        return true;
    }

}
