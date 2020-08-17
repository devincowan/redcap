const node = {
    name: '',
    children: [
        {
            name: 'test',
            children: [
                {
                    name: 'another test',
                    children: [
                        {
                            name: 'asdasdasd',
                            children: [
                                {
                                    name: 'inner',
                                    children: [],
                                    checked: true,
                                },
                                {
                                    name: 'other inner',
                                    children: [],
                                    checked: true,
                                },
                            ]
                        },
                        {
                            name: 'pappapapa',
                            children: [
                                {
                                    name: 'cacacaca',
                                    children: [],
                                    checked: true,
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        {
            name: 'test 1',
            children: [
                {
                    name: 'another test',
                    children: [
                        {
                            name: 'asdasdasd',
                            children: [],
                            checked: false,
                        }
                    ]
                }
            ]
        },
        {
            name: 'test 2',
            children: [
                {
                    name: 'another test',
                    children: [
                        {
                            name: 'asdasdasd',
                            children: [],
                            checked: true,
                        }
                    ]
                }
            ]
        },
    ]
}
export default node