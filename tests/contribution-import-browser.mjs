/** AI packet imports must not replace independently supplied volunteer controls. */
export async function contributionImportQa({page,url,fixture,runPhp,check,shot}) {
  for (const operation of ['load', 'preview']) {
    await page.goto(url + '/contribute');
    const details = 'Synthetic volunteer disclosure: I supplied extra sources before this import.';
    await page.locator('[name=human_level]').selectOption('influenced');
    await page.locator('[name=human_details]').fill(details);
    const csrf = await page.locator('[name=csrf]').inputValue();
    const packet = {
      kind: 'Original research', target_id: null,
      summary: 'Synthetic manual import boundary regression',
      body: 'This synthetic contribution tests the import boundary only. No scientific conclusion is established.',
      methodology: 'Exercise the actual browser import and independent volunteer declaration.',
      sources: 'Provided synthetic fixture only; no external scientific source is asserted.',
      uncertainty: 'This regression does not establish scientific evidence.',
      limitations: 'Local browser workflow testing only.',
      previous_work: 'No earlier scientific work was used.',
      open_questions: 'Real research remains outside this synthetic test.',
      next_actions: 'Retain the disclosed influence for moderation.',
      suggested_status: 'Still Unsolved', safety: 'Only local synthetic form input was used.',
      recommendation: '', display_name: 'Synthetic import QA', linkedin: '', github: '', website: '',
      human_interaction: {level: 'none', details: ''},
      metadata: {
        provider: 'Synthetic test provider', model: 'fixture-model-1', harness: 'Browser QA harness',
        harness_version: '1', reasoning: 'standard', input_tokens: 120, output_tokens: 80, total_tokens: 200,
        measurement: 'synthetic fixture', duration: '1 minute', tools: ['Browser', 'Test harness'],
        prompt_version: 'v1/context-qa', human_level: 'none'
      },
      // These keys are deliberately outside the packet contract.
      human_level: 'none', human_details: '', ai_level: 'none',
      ai_details: 'A direct alias must not replace the mapped AI declaration.',
      terms: 'packet-terms', tool_terms: 'packet-software-terms', csrf: 'packet-csrf'
    };
    packet.evidence=JSON.parse(runPhp(['tests/qa-state.php','evidence',packet.body,packet.methodology,packet.sources]));
    await page.locator('[name=json]').fill(JSON.stringify(packet));
    await page.getByRole('button', {name: operation === 'load' ? 'Load JSON into fields' : 'Preview contribution', exact: true}).click();
    check(operation + ' preserves independent volunteer level', await page.locator('[name=human_level]').inputValue() === 'influenced');
    check(operation + ' preserves independent volunteer explanation', await page.locator('[name=human_details]').inputValue() === details);
    check(operation + ' preserves CSRF', await page.locator('[name=csrf]').inputValue() === csrf);
    for (const name of ['terms', 'tool_terms']) {
      check(operation + ' cannot set or alter ' + name, !await page.locator('[name=' + name + ']').isChecked() && await page.locator('[name=' + name + ']').inputValue() === '1');
    }
    check(operation + ' maps the packet AI declaration separately', await page.locator('[name=ai_level]').inputValue() === 'none' && await page.locator('[name=ai_details]').inputValue() === '');
    check(operation + ' imports allowed research fields', await page.locator('[name=summary]').inputValue() === packet.summary && await page.locator('[name=body]').inputValue() === packet.body);
    check(operation + ' imports allowed provenance fields', await page.locator('[name="metadata[model]"]').inputValue() === packet.metadata.model && await page.locator('[name="metadata[tools]"]').inputValue() === 'Browser, Test harness');
    if (operation === 'preview') {
      const preview = JSON.parse(await page.locator('#preview-content').innerText());
      check('Preview retains the actual volunteer declaration', preview.human_level === 'influenced' && preview.human_details === details);
    }
    const disclosure = page.locator('fieldset').filter({has: page.locator('[name=human_level]')});
    await disclosure.scrollIntoViewIfNeeded();
    await shot(page, 'manual-import-' + operation + '-preserved');
    await page.locator('[name=terms]').check();
    await page.locator('[name=tool_terms]').check();
    await page.getByRole('button', {name: 'Submit contribution for approval', exact: true}).click();
    await page.getByRole('heading', {name: 'Contribution recorded — awaiting moderation', exact: true}).waitFor();
    const id = new URL(page.url()).pathname.split('/').at(-2);
    const row = JSON.parse(runPhp(['tests/browser-return-state.php', 'contribution', fixture.id, id]));
    const influence = JSON.parse(row.human_influence);
    check(operation + ' persists the genuine volunteer declaration', influence.human_disclosure.level === 'influenced' && influence.human_disclosure.details === details);
    check(operation + ' retains influence review rather than auto-publishing', row.moderation === 'Needs Additional Review' && influence.review_status === 'needed');
    check(operation + ' retains the manual method label', row.submission_method === 'manual');
    const context = await (await page.request.get(url + '/context.json')).json();
    check(operation + ' excludes the held contribution from public evidence', !context.contributions.some(c => c.id === id));
  }
}
