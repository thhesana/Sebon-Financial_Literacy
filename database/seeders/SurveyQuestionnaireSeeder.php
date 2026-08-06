<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds SurveySection / SurveyQuestion / SurveyQuestionOption
 * from the GMW-2026 questionnaire document when tables are empty.
 */
class SurveyQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('SurveyQuestion')->count() > 0) {
            $this->command?->info('Survey questions already exist. Skipping.');

            return;
        }

        $now = now();

        $sectionA = DB::table('SurveySection')->insertGetId([
            'SurveySectionName' => 'SECTION A — Personal Information',
            'SurveySectionCreated' => $now,
        ], 'SurveySectionId');

        $sectionB = DB::table('SurveySection')->insertGetId([
            'SurveySectionName' => 'SECTION B — Financial Education Information',
            'SurveySectionCreated' => $now,
        ], 'SurveySectionId');

        $sectionC = DB::table('SurveySection')->insertGetId([
            'SurveySectionName' => 'SECTION C — Capital Market Knowledge & Attitude',
            'SurveySectionCreated' => $now,
        ], 'SurveySectionId');

        $order = 0;
        $add = function (int $sectionId, string $code, string $text, string $type, array $options = [], bool $required = true, ?int $parentQuestionId = null, ?int $parentTriggerOptionId = null) use (&$order, $now) {
            $order++;
            $qid = DB::table('SurveyQuestion')->insertGetId([
                'SurveySectionId' => $sectionId,
                'QuestionCode' => $code,
                'QuestionText' => $text,
                'QuestionType' => $type,
                'IsRequired' => $required ? 1 : 0,
                'DisplayOrder' => $order,
                'ParentQuestionId' => $parentQuestionId,
                'ParentTriggerOptionId' => $parentTriggerOptionId,
                'IsActive' => 1,
                'CreatedDate' => $now,
            ], 'QuestionId');

            $optOrder = 0;
            foreach ($options as $opt) {
                $optOrder++;
                $label = is_array($opt) ? $opt['text'] : $opt;
                $allowsOther = is_array($opt) ? (int) ($opt['other'] ?? 0) : 0;
                DB::table('SurveyQuestionOption')->insert([
                    'QuestionId' => $qid,
                    'OptionText' => $label,
                    'DisplayOrder' => $optOrder,
                    'AllowsOtherText' => $allowsOther,
                    'IsActive' => 1,
                ]);
            }

            return $qid;
        };

        $add($sectionA, '1', 'Name of the participant (optional)', 'text', [], false);
        $add($sectionA, '2', 'Gender', 'radio', ['Male', 'Female', 'Prefer not to say']);
        $add($sectionA, '3', 'Age Group', 'radio', ['16–20', '21–25', '26–30', '31–35']);
        $add($sectionA, '4', 'Caste / Ethnicity', 'text', [], false);
        $add($sectionA, '5', 'Mother Tongue', 'text', [], false);
        $add($sectionA, '6', 'Highest Educational Qualification', 'radio', [
            'SEE (Grade 10)', '10+2 / A-Level', "Bachelor's", "Master's or above",
        ]);
        $add($sectionA, '7', 'Education Stream', 'radio', [
            'Management / Commerce', 'Science', 'Humanities', ['text' => 'Others', 'other' => 1],
        ]);
        $add($sectionA, '8', 'Current Professional Status', 'radio', [
            'Student', 'Employed', 'Self-Employed', 'Unemployed',
        ]);
        $add($sectionA, '9a', 'Profession of Parents — Father', 'text', [], false);
        $add($sectionA, '9b', 'Profession of Parents — Mother', 'text', [], false);
        $add($sectionA, '10', 'Is anyone of your family involved with foreign employment?', 'checkbox', [
            'Father', 'Mother', 'Brother', 'Sister', ['text' => 'Other', 'other' => 1], 'None',
        ]);

        $add($sectionB, '11', 'Are you aware of the following entities? (Mark all that apply)', 'checkbox', [
            'Nepal Rastra Bank (NRB)', 'SEBON', 'Nepal Stock Exchange (NEPSE)', 'CDSC / Meroshare', 'None of the above',
        ]);
        $add($sectionB, '12', 'Have you opened any of the following accounts? (Mark all that apply)', 'checkbox', [
            'Bank Account', 'Demat Account (D-Mat)', 'Trading Account', 'None',
        ]);
        $add($sectionB, '13', 'Are you aware of the following financial instruments? (Mark all that apply)', 'checkbox', [
            'IPO (Initial Public Offering)', 'Mutual Fund', 'Debenture / Bond', 'None of the above',
        ]);
        $add($sectionB, '14', 'Which of the following financial instruments is riskier?', 'radio', [
            'IPO (Initial Public Offering)', 'Mutual Fund', 'Debenture / Bond', 'None of the above',
        ]);
        $add($sectionB, '15', 'Have you ever applied for any of the following? (Mark all that apply)', 'checkbox', [
            'IPO', 'Mutual Fund', 'Debenture', 'None',
        ]);
        $add($sectionB, '16', 'Which social media platform do you primarily use to get information about the capital market? (Mark all that apply)', 'checkbox', [
            'Facebook', 'TikTok', 'YouTube', ['text' => 'Others', 'other' => 1],
        ]);
        $add($sectionB, '17', 'Which alternative source do you use to get information about the capital market? (Mark all that apply)', 'checkbox', [
            'Television / Radio', 'Newspaper / Magazines', 'Family / Friends', 'School / College', 'SEBON / NEPSE Website',
        ]);
        $add($sectionB, '18', 'Does your school / college conduct classes or programs on financial literacy?', 'radio', [
            'Yes', 'No', 'Not sure',
        ]);
        $q19 = $add($sectionB, '19', 'Have you previously attended any financial literacy program or training?', 'radio', [
            'Yes', 'No',
        ]);
        $yesOptionId = DB::table('SurveyQuestionOption')
            ->where('QuestionId', $q19)
            ->where('OptionText', 'Yes')
            ->value('OptionId');
        $add($sectionB, '19a', 'If Yes, conducted by', 'text', [], false, $q19, $yesOptionId);

        $add($sectionC, '20', 'How would you rate your overall knowledge of the capital market?', 'radio', [
            'No knowledge', 'Basic knowledge', 'Moderate knowledge', 'Advanced knowledge',
        ]);
        $add($sectionC, '21', 'Do you know the primary function of the Nepal Stock Exchange (NEPSE)?', 'radio', [
            'Yes, clearly', 'Somewhat', 'No',
        ]);
        $add($sectionC, '22', 'Have you ever bought or sold shares listed on NEPSE?', 'radio', [
            'Yes', 'No, but I plan to', 'No, and I have no plans to',
        ]);
        $add($sectionC, '23', 'Do you understand the difference between primary and secondary markets?', 'radio', [
            'Yes', 'Somewhat', 'No',
        ]);
        $add($sectionC, '24', 'Do you understand the difference between stock broker and merchant banker?', 'radio', [
            'Yes', 'Somewhat', 'No',
        ]);
        $add($sectionC, '25', 'Do you understand the difference between ASBA and C-ASBA?', 'radio', [
            'Yes', 'Somewhat', 'No',
        ]);
        $add($sectionC, '26', 'In your opinion, what is the biggest barrier to youth participation in the capital market? (Choose the most relevant option)', 'radio', [
            'Lack of knowledge / awareness',
            'Lack of capital / savings',
            'Fear of risk / loss',
            'Complex process',
            'Lack of trust',
            ['text' => 'Other', 'other' => 1],
        ]);

        $this->command?->info('GMW-2026 questionnaire seeded successfully.');
    }
}
