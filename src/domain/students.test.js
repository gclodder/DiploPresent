import { describe, expect, it } from 'vitest'
import { normalizeJson, parseCsv, renumber, safeListName, selectStudents } from './students'

describe('student imports', () => {
  it('normaliseert oude DataTables JSON', () => {
    const students = normalizeJson({
      body: [[0, '', '12345', 'Ada Lovelace', 'Lovelace', 'ABC', 'Ja', '']],
    })

    expect(students).toEqual([
      {
        position: 1,
        studentNumber: '12345',
        fullName: 'Ada Lovelace',
        firstName: '',
        lastName: 'Lovelace',
        mentor: 'ABC',
        cumLaude: true,
        group: '',
      },
    ])
  })

  it('leest puntkomma-CSV met Nederlandse kolomnamen', () => {
    const students = parseCsv(
      'Leerlingnummer;Roepnaam;Volledige Naam;Achternaam;Afkorting;Cum Laude;Stamgroep\n12345;Ada;Ada Lovelace;Lovelace;ABC;ja;v6a',
    )

    expect(students[0].firstName).toBe('Ada')
    expect(students[0].fullName).toBe('Ada Lovelace')
    expect(students[0].cumLaude).toBe(true)
    expect(students[0].group).toBe('v6a')
  })

  it('hernummert en maakt een veilige lijstnaam', () => {
    const students = [{ position: 8 }, { position: 9 }]
    expect(renumber(students).map((student) => student.position)).toEqual([1, 2])
    expect(safeListName(['A/B', 'C D'])).toBe('A_B_C_D.json')
  })

  it('combineert meerdere mentorgroepen in de gekozen bestandsvolgorde', () => {
    const students = [
      { studentNumber: '1', mentor: 'RLU' },
      { studentNumber: '2', mentor: 'FBR' },
      { studentNumber: '3', mentor: 'CAT' },
      { studentNumber: '4', mentor: 'RLU' },
    ]

    expect(selectStudents(students, 'mentor', ['RLU', 'FBR']).map((student) => student.studentNumber))
      .toEqual(['1', '2', '4'])
    expect(safeListName(['RLU', 'FBR'])).toBe('RLU_FBR.json')
  })
})
