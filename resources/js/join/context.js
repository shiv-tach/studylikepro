/**
 * The invite survey and the account step are two pages of the same journey, so
 * they share one injection key: whichever app boots provides its island config
 * and the live form under it, and every child (question cards, rail, summary,
 * mobile bar) reads them from here instead of prop-drilling.
 */
export const SURVEY_KEY = Symbol('join-survey');
