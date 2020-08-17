/**
 * components are lazy loaded as the routes are visited
 */
const routes = [
    // { path: '/', redirect: {name: 'newsletter'} },
    { path: '', component: () => import('@/layouts/MainLayout'),
        children: [
            { path: '', name: 'home', component: () => import('@/pages/Home') },
            { path: 'fhir-resource', name: 'fhir-resource', component: () => import('@/pages/FhirResource'),
                children: [
                    { path: 'patient/:method', name: 'patient', components: {table: () => import('@/components/patient/PatientTable')}, meta: { resource_type: 'Patient'}},
                    { path: 'medication-order/:method', name: 'medication-order', components: {
                            extra_fields: () => import('@/components/medication-order/MedicationOrderFields'),
                            table: () => import('@/components/medication-order/MedicationOrderTable'),
                        }, meta: { resource_type: 'MedicationOrder'}, 
                    },
                    { path: 'observation/:method', name: 'observation', components: {
                        extra_fields: () => import('@/components/observation/ObservationFields'),
                        table: () => import('@/components/observation/ObservationTable'),
                        }, meta: { resource_type: 'Observation'}},
                    { path: 'condition/:method', name: 'condition', components: {table: () => import('@/components/condition/ConditionTable')}, meta: { resource_type: 'Condition'}},
                    { path: 'allergy-intolerance/:method', name: 'allergy-intolerance', components: {table: () => import('@/components/allergy/AllergyTable')}, meta: { resource_type: 'AllergyIntolerance'}},
                ]
            },
            { path: 'fhir-tool', name: 'fhir-tool', component: () => import('@/pages/FhirTool')},

            { path: '/help', name: 'help', component: () => import('@/pages/Help') },
            { path: '/fhir_metadata', name: 'fhir_metadata', component: () => import('@/pages/FhirMetadata') },
            { path: '/project_info', name: 'project_info', component: () => import('@/pages/ProjectInfo') },
            { path: '/string-identifier', name: 'string-identifier', component: () => import('@/pages/StringIdentifier') },

            { path: "*", name: 'not_found', component: () => import('@/pages/PageNotFound')  }
        ]
    },
]

export default routes