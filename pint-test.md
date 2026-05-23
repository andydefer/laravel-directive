# Pint Formatting Test Report
*Generated: sam. 23 mai 2026 18:34:48 WAT*


  ⨯⨯.......................⨯.⨯⨯.⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯.....⨯⨯⨯⨯⨯..⨯...⨯⨯...⨯⨯⨯⨯.⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯.⨯⨯⨯..⨯....⨯.⨯.⨯⨯.⨯

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................ 111 files, 62 style issues  
  ⨯ src/AbstractDirective.php                                                                           class_attributes_separation, new_with_parentheses, blank_line_before_statement  
  ⨯ src/Collections/ReplacementCollection.php                                                                                        new_with_parentheses, blank_line_before_statement  
  ⨯ src/Config/DirectiveConfig.php                                                                                               concat_space, braces_position, single_line_empty_body  
  ⨯ src/DirectiveKernel.php                              new_with_parentheses, braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement  
  ⨯ src/DirectiveServiceProvider.php                                                                                   new_with_parentheses, concat_space, blank_line_before_statement  
  ⨯ src/Directives/MakeDirective.php                                                new_with_parentheses, concat_space, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Enums/RenderType.php                                                                                                                                              single_quote  
  ⨯ src/Enums/ShortOption.php                                                                                            concat_space, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Services/DirectiveDiscoveryService.php               concat_space, braces_position, phpdoc_separation, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Services/DirectiveExecutionService.php                                              new_with_parentheses, braces_position, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Services/DirectiveInteractionService.php                                                                  braces_position, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Services/DirectiveNamingService.php                                                                                                                 concat_space, phpdoc_align  
  ⨯ src/Services/DirectiveRegistrar.php                 class_attributes_separation, new_with_parentheses, cast_spaces, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Services/SignatureValidationService.php                                                                                                      not_operator_with_successor_space  
  ⨯ src/Strategies/ConflictRenderStrategy.php                                                      new_with_parentheses, single_quote, concat_space, not_operator_with_successor_space  
  ⨯ src/Strategies/DisplayMessageRenderStrategy.php                                                                            new_with_parentheses, not_operator_with_successor_space  
  ⨯ src/Strategies/HelpRenderStrategy.php                                                                                                                         new_with_parentheses  
  ⨯ src/Strategies/Input/ConfirmationStrategy.php                                                                                      concat_space, not_operator_with_successor_space  
  ⨯ src/Strategies/Input/SimpleQuestionStrategy.php                                                       concat_space, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Strategies/Input/UserChoiceStrategy.php                                                                                                      not_operator_with_successor_space  
  ⨯ src/Strategies/ListRenderStrategy.php                                                           new_with_parentheses, concat_space, cast_spaces, not_operator_with_successor_space  
  ⨯ src/Strategies/MessageRenderStrategy.php                                                      new_with_parentheses, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Strategies/NotFoundRenderStrategy.php                                                     new_with_parentheses, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Strategies/TableRenderStrategy.php                                                                       new_with_parentheses, concat_space, not_operator_with_successor_space  
  ⨯ src/Strategies/ValidationErrorRenderStrategy.php                                              new_with_parentheses, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Tasks/CreateDirectiveFileTask.php                                                                 class_attributes_separation, concat_space, not_operator_with_successor_space  
  ⨯ src/Tasks/RenderTask.php                                                                                new_with_parentheses, no_unused_imports, not_operator_with_successor_space  
  ⨯ src/config/directive.php                                                                                                                                              concat_space  
  ⨯ tests/Feature/DirectiveDiscoveryServiceIntegrationTest.php                                                         class_attributes_separation, new_with_parentheses, concat_space  
  ⨯ tests/Feature/DirectiveIntegrationTest.php                                                                                     new_with_parentheses, concat_space, ordered_imports  
  ⨯ tests/Fixtures/Directives/TestDirective.php                                                                                                            class_attributes_separation  
  ⨯ tests/Fixtures/Directives/TestEchoDirective.php                                                                                                               new_with_parentheses  
  ⨯ tests/Fixtures/RegisteredDirectives/TestPackageDirective.php                                                                                                  new_with_parentheses  
  ⨯ tests/Fixtures/Tasks/TestableDisplayValidationErrorTask.php                                                                                                           concat_space  
  ⨯ tests/TestCase.php                                                                                                                                 concat_space, no_unused_imports  
  ⨯ tests/Unit/AbstractDirectiveTest.php                                                                                             class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Collections/ReplacementCollectionTest.php                                                                                                          new_with_parentheses  
  ⨯ tests/Unit/DirectiveKernelTest.php                                                                                                                     class_attributes_separation  
  ⨯ tests/Unit/DirectiveServiceProviderTest.php                                       class_attributes_separation, new_with_parentheses, fully_qualified_strict_types, ordered_imports  
  ⨯ tests/Unit/Directives/MakeDirectiveTest.php                                                                     class_attributes_separation, new_with_parentheses, ordered_imports  
  ⨯ tests/Unit/Enums/RenderTypeTest.php                                                                                                                                   single_quote  
  ⨯ tests/Unit/Services/DirectiveExecutionServiceTest.php                                               class_attributes_separation, new_with_parentheses, blank_line_before_statement  
  ⨯ tests/Unit/Services/DirectiveHydratorServiceTest.php                                                                             class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveInteractionServiceTest.php                                                                          class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveNamingServiceTest.php                                                                                                            new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveRegistrarTest.php                                                                                                                new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveRendererServiceTest.php                                                                             class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Services/SignatureValidationServiceTest.php                                                                                                        new_with_parentheses  
  ⨯ tests/Unit/Strategies/ConflictRenderStrategyTest.php                                                                                                          new_with_parentheses  
  ⨯ tests/Unit/Strategies/DisplayMessageRenderStrategyTest.php                                                                                                    new_with_parentheses  
  ⨯ tests/Unit/Strategies/HelpRenderStrategyTest.php                                                                                                              new_with_parentheses  
  ⨯ tests/Unit/Strategies/Input/ConfirmationStrategyTest.php                                   class_attributes_separation, new_with_parentheses, concat_space, php_unit_method_casing  
  ⨯ tests/Unit/Strategies/Input/SimpleQuestionStrategyTest.php                                                         class_attributes_separation, new_with_parentheses, concat_space  
  ⨯ tests/Unit/Strategies/Input/UserChoiceStrategyTest.php                                                             class_attributes_separation, new_with_parentheses, concat_space  
  ⨯ tests/Unit/Strategies/ListRenderStrategyTest.php                                                                                                              new_with_parentheses  
  ⨯ tests/Unit/Strategies/MessageRenderStrategyTest.php                                                                                                           new_with_parentheses  
  ⨯ tests/Unit/Strategies/NotFoundRenderStrategyTest.php                                                                                                          new_with_parentheses  
  ⨯ tests/Unit/Strategies/TableRenderStrategyTest.php                                                                                                             new_with_parentheses  
  ⨯ tests/Unit/Strategies/ValidationErrorRenderStrategyTest.php                                                                                                   new_with_parentheses  
  ⨯ tests/Unit/Tasks/CreateDirectiveFileTaskTest.php                                                                   class_attributes_separation, new_with_parentheses, concat_space  
  ⨯ tests/Unit/Tasks/InputTaskTest.php                                                                                                       class_attributes_separation, concat_space  
  ⨯ tests/Unit/Tasks/RenderTaskTest.php                                                                                                                           new_with_parentheses  

