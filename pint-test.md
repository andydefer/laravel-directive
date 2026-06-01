# Pint Formatting Test Report
*Generated: lun. 01 juin 2026 17:16:33 WAT*


  ⨯.............⨯.⨯.....⨯.⨯⨯⨯...⨯⨯⨯⨯⨯⨯..........⨯...⨯.....⨯⨯⨯⨯⨯⨯⨯⨯⨯.......⨯..⨯.⨯⨯..⨯⨯⨯⨯⨯⨯......⨯.......⨯.⨯⨯⨯⨯⨯⨯⨯⨯⨯...........................

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................ 139 files, 45 style issues  
  ⨯ src/AbstractDirective.php                                                                       class_attributes_separation, new_with_parentheses, phpdoc_separation, phpdoc_align  
  ⨯ src/Collections/AbstractItemCollection.php                                                                                          phpdoc_separation, blank_line_before_statement  
  ⨯ src/Collections/AbstractKeyValueCollection.php                                                                                                                   phpdoc_separation  
  ⨯ src/Collections/ReplacementCollection.php                                                                                    no_superfluous_phpdoc_tags, phpdoc_trim, phpdoc_align  
  ⨯ src/Collections/RowCollection.php                                                                                                    no_unused_imports, blank_line_after_namespace  
  ⨯ src/DirectiveKernel.php braces_position, phpdoc_separation, no_unused_imports, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement, phpdoc_ali…  
  ⨯ src/DirectiveServiceProvider.php                                                                                                                   concat_space, no_unused_imports  
  ⨯ src/Records/ParsedDirectiveRecord.php                                                                                   braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/Records/RenderRecord.php                                                                                            braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/Services/DirectiveDiscoveryService.php     class_attributes_separation, new_with_parentheses, concat_space, phpdoc_separation, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Services/DirectiveExecutionService.php braces_position, phpdoc_separation, no_unused_imports, not_operator_with_successor_space, single_line_empty_body, blank_line_before_st…  
  ⨯ src/Services/DirectiveHydratorService.php                                    braces_position, phpdoc_separation, single_line_empty_body, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/DirectiveInteractionService.php                                 braces_position, phpdoc_separation, single_line_empty_body, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/DirectiveNamingService.php class_attributes_separation, concat_space, phpdoc_separation, not_operator_with_successor_space, blank_line_before_statement, phpdoc_alig…  
  ⨯ src/Services/DirectiveParserService.php new_with_parentheses, function_declaration, no_superfluous_phpdoc_tags, concat_space, braces_position, phpdoc_separation, not_operator_wi…  
  ⨯ src/Services/DirectiveRendererService.php                                                 braces_position, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Services/LaravelBootstrapper.php    class_attributes_separation, concat_space, phpdoc_separation, not_operator_with_successor_space, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/SignatureValidationService.php                                        class_attributes_separation, phpdoc_separation, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Strategies/ListRenderStrategy.php                                                    concat_space, unary_operator_spaces, no_unused_imports, not_operator_with_successor_space  
  ⨯ src/Tasks/InputTask.php                                                                                                                            phpdoc_separation, phpdoc_align  
  ⨯ src/Tasks/RenderTask.php                                                                  new_with_parentheses, phpdoc_separation, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Testing/ClosureDirective.php                                                                                                                                      phpdoc_align  
  ⨯ src/Testing/InteractsWithDirectives.php class_attributes_separation, new_with_parentheses, concat_space, phpdoc_separation, not_operator_with_successor_space, blank_line_before_…  
  ⨯ src/Testing/TestDirectiveDiscoveryService.php    new_with_parentheses, no_superfluous_phpdoc_tags, phpdoc_separation, phpdoc_trim, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Testing/TestDirectiveRegistry.php                                                                                 phpdoc_separation, blank_line_before_statement, phpdoc_align  
  ⨯ tests/Feature/DirectiveIntegrationTest.php                                                                         class_attributes_separation, concat_space, no_extra_blank_lines  
  ⨯ tests/Unit/AbstractDirectiveTest.php                                                                                             class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Collections/AbstractKeyValueCollectionTest.php                                                                                php_unit_method_casing, no_unused_imports  
  ⨯ tests/Unit/DirectiveServiceProviderTest.php                                                                                   class_definition, braces_position, no_unused_imports  
  ⨯ tests/Unit/Services/DirectiveDiscoveryServiceTest.php             class_attributes_separation, new_with_parentheses, concat_space, not_operator_with_successor_space, phpdoc_align  
  ⨯ tests/Unit/Services/DirectiveExecutionServiceTest.php                                                              class_attributes_separation, new_with_parentheses, concat_space  
  ⨯ tests/Unit/Services/DirectiveHydratorServiceTest.php                      class_attributes_separation, new_with_parentheses, no_superfluous_phpdoc_tags, phpdoc_trim, phpdoc_align  
  ⨯ tests/Unit/Services/DirectiveInteractionServiceTest.php                                                                          class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveNamingServiceTest.php                                                                                                            new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveParserServiceTest.php                                                                                                            new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveRendererServiceTest.php                                  class_attributes_separation, new_with_parentheses, php_unit_method_casing, no_unused_imports  
  ⨯ tests/Unit/Services/LaravelBootstrapperTest.php                                                                            new_with_parentheses, not_operator_with_successor_space  
  ⨯ tests/Unit/Services/SignatureValidationServiceTest.php                                                                  new_with_parentheses, concat_space, php_unit_method_casing  
  ⨯ tests/Unit/Strategies/ListRenderStrategyTest.php                                                                                                                 no_unused_imports  
  ⨯ tests/Unit/Tasks/InputTaskTest.php                                                                      class_attributes_separation, concat_space, phpdoc_separation, phpdoc_align  
  ⨯ tests/Unit/Tasks/RenderTaskTest.php                                                                                                                           new_with_parentheses  
  ⨯ tests/Unit/Testing/ClosureDirectiveTest.php                                                                new_with_parentheses, function_declaration, blank_line_before_statement  
  ⨯ tests/Unit/Testing/InteractsWithDirectivesTest.php                                                                                       concat_space, blank_line_before_statement  
  ⨯ tests/Unit/Testing/TestDirectiveDiscoveryServiceTest.php                                                                         class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Testing/TestDirectiveRegistryTest.php                                                                                                                 no_unused_imports  

